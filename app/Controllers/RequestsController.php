<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\RequestRepository;
use App\Repositories\P4NominalValidationRepository;
use App\Services\RequestService;
use App\Services\CentralRequestReview;
use App\Services\P4RequestTracking;
use App\Services\SpreadsheetReader;
use App\Services\BootsP4WorkbookPreview;
use App\Services\NominalBulkRequestService;
use App\Services\P4NominalStagedFile;
use App\Services\P4NominalXlsxTemplate;
use App\Services\RrhhEmployeeDirectory;
use App\Support\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class RequestsController
{
    private RequestRepository $repository;
    private RequestService $service;
    private NominalBulkRequestService $bulk;

    public function __construct(private Database $database)
    {
        $this->repository = new RequestRepository($database->connection('sindesg'));
        $directory = new RrhhEmployeeDirectory($database);
        $this->service = new RequestService($this->repository, $directory);
        $this->bulk = new NominalBulkRequestService(
            $this->repository,
            $directory,
            new P4NominalValidationRepository($database->connection('sindesg'))
        );
    }

    public function handle(
        string $action,
        array $query,
        array $post,
        string $method
    ): array {
        try {
            if (strtoupper($method) === 'POST') {
                if (!csrf_valid(isset($post['_token']) ? (string) $post['_token'] : null)) {
                    throw new InvalidArgumentException('La sesión del formulario venció. Intente nuevamente.');
                }

                return $this->handlePost($action, $post);
            }

            return $this->handleGet($action, $query);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            flash('error', $exception->getMessage());

            $requestId = (int) ($post['request_id'] ?? $query['id'] ?? 0);
            $this->redirect($action === 'preview-boots'
                ? '?module=requests&action=import-boots'
                : ($requestId > 0
                    ? '?module=requests&action=show&id=' . $requestId
                    : '?module=requests&action=new'));
        } catch (Throwable $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/requests/unavailable.php',
                'title' => 'Solicitudes',
                'data' => [
                    'requestError' => 'El módulo de Solicitudes aún no está disponible en esta instalación. Aplique la migración 009_requests.sql y vuelva a intentarlo.',
                ],
            ];
        }
    }

    private function handleGet(string $action, array $query): array
    {
        if ($action === 'download-nominal-template') {
            // Excel nativo evita que Windows/Excel regional abra el CSV
            // completo como una sola columna.
            $filename = tempnam(sys_get_temp_dir(), 'sindesg_p4_');
            if ($filename === false) {
                throw new RuntimeException('No se pudo crear la plantilla temporal.');
            }
            try {
                (new P4NominalXlsxTemplate())->write($filename);
                if (!is_file($filename) || filesize($filename) === 0) {
                    throw new RuntimeException('No se pudo construir la plantilla XLSX.');
                }
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="SINDESG_Plantilla_Dotacion_P4.xlsx"');
                header('Content-Length: ' . filesize($filename));
                header('Cache-Control: no-store, no-cache, must-revalidate');
                if (readfile($filename) === false) {
                    throw new RuntimeException('No fue posible enviar el archivo Excel.');
                }
            } finally {
                @unlink($filename);
            }
            exit;
        }

        if ($action === 'import-boots') {
            return [
                'view' => SINDESG_ROOT . '/app/Views/requests/boots-import.php',
                'title' => 'Prevalidación de dotación P4',
                'data' => ['bootsPreview' => null],
            ];
        }

        if ($action === 'new') {
            return [
                'view' => SINDESG_ROOT . '/app/Views/requests/form.php',
                'title' => 'Nueva solicitud',
                'data' => [
                    'activeP4s' => $this->repository->activeP4s(),
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                    'defaultRequestedAt' => date('Y-m-d\TH:i'),
                ],
            ];
        }

        if ($action === 'central-review') {
            $requestId = (int) ($query['id'] ?? 0);
            $request = $this->repository->find($requestId);
            if ($request === null) {
                throw new InvalidArgumentException('La solicitud no existe.');
            }
            $items = $this->repository->items($requestId);
            $groups = CentralRequestReview::groups($items);
            return [
                'view' => SINDESG_ROOT . '/app/Views/requests/central-review.php',
                'title' => 'Revisión Central · ' . $request['request_code'],
                'data' => [
                    'serviceRequest' => $request,
                    'variantGroups' => $groups,
                    'reviewTotal' => array_sum(array_map(
                        static fn (array $g): float => (float) $g['requested'], $groups
                    )),
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                    'tracking' => P4RequestTracking::build(
                        $request,
                        $this->requestProgressQuantities($requestId, $items),
                        $this->repository->transferProgress($requestId)
                    ),
                ],
            ];
        }

        if ($action === 'show') {
            $requestId = (int) ($query['id'] ?? 0);
            $request = $this->repository->find($requestId);

            if ($request === null) {
                throw new InvalidArgumentException('La solicitud solicitada no existe.');
            }
            $requestItems = $this->repository->items($requestId);

            return [
                'view' => SINDESG_ROOT . '/app/Views/requests/show.php',
                'title' => (string) $request['request_code'],
                'data' => [
                    'serviceRequest' => $request,
                    'requestItems' => $requestItems,
                    'requestCommitmentEvidence' => $this->repository->commitmentEvidence($requestId),
                    'requestTracking' => P4RequestTracking::build(
                        $request,
                        $this->requestProgressQuantities($requestId, $requestItems),
                        $this->repository->transferProgress($requestId)
                    ),
                    'variantOptions' => $this->repository->variantOptions(),
                    'nominalProducts' => $this->repository->nominalProducts(),
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                    'nominalPreview' => (
                        isset($_SESSION['p4_nominal_review']['report'])
                        && (int) ($_SESSION['p4_nominal_review']['report']['request_id'] ?? 0) === $requestId
                        && (int) ($_SESSION['p4_nominal_review']['at'] ?? 0) >= time() - P4NominalStagedFile::TTL
                        && (int) ($_SESSION['p4_nominal_review']['user_id'] ?? 0) === (int) (sindesg_user()['id'] ?? 0)
                    ) ? $_SESSION['p4_nominal_review']['report'] : null,
                    'nominalReviewFileReady' => (
                        !empty($_SESSION['p4_nominal_review']['file'])
                        && (int) ($_SESSION['p4_nominal_review']['report']['request_id'] ?? 0) === $requestId
                        && (int) ($_SESSION['p4_nominal_review']['at'] ?? 0) >= time() - P4NominalStagedFile::TTL
                        && (int) ($_SESSION['p4_nominal_review']['user_id'] ?? 0) === (int) (sindesg_user()['id'] ?? 0)
                    ),
                ],
            ];
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/requests/index.php',
            'title' => 'Solicitudes',
            'data' => [
                'requestStats' => $this->repository->stats(),
                'serviceRequests' => \App\Services\P4WarehouseScope::filter($this->repository->recent(100), \App\Services\P4WarehouseScope::current($this->database), 'p4_warehouse_id'),
            ],
        ];
    }

    private function handlePost(string $action, array $post): array
    {
        if ($action === 'add-nominal-bulk') {
            $requestId = (int) ($post['request_id'] ?? 0);
            $employeeIds = $post['employee_ids'] ?? [];
            if (!is_array($employeeIds)) {
                throw new InvalidArgumentException('Seleccione funcionarios válidos.');
            }
            $count = $this->bulk->addSelected(
                $requestId,
                (int) ($post['product_variant_id'] ?? 0),
                $employeeIds
            );
            flash('success', $count . ' funcionarios agregados a la solicitud nominal.');
            $this->redirect('?module=requests&action=show&id=' . $requestId);
        }

        if ($action === 'validate-nominal-selected') {
            if (!sindesg_can('requests.manage')) {
                throw new InvalidArgumentException('No tiene permiso de aprobar incidencias P4.');
            }
            $requestId = (int) ($post['request_id'] ?? 0);
            $session = $_SESSION['p4_nominal_review'] ?? null;
            if (!is_array($session)
                || (int) ($session['at'] ?? 0) < time() - 3600
                || !is_array($session['report'] ?? null)
                || (int) ($session['report']['request_id'] ?? 0) !== $requestId
                || (int) ($session['user_id'] ?? 0) !== (int) (sindesg_user()['id'] ?? 0)) {
                throw new InvalidArgumentException('La prevalidación venció. Adjunte de nuevo el Excel.');
            }
            $selected = $post['review_rows'] ?? [];
            if (!is_array($selected) || count($selected) < 1 || count($selected) > 100
                || (string) ($post['confirm_bulk'] ?? '') !== '1') {
                throw new InvalidArgumentException(
                    'Seleccione las incidencias revisadas para confirmarlas bajo responsabilidad del P4.'
                );
            }
            $rows = [];
            foreach ($selected as $value) {
                $value = (string) $value;
                if (!ctype_digit($value) || (int) $value <= 0) {
                    throw new InvalidArgumentException('Selección de filas inválida.');
                }
                $rows[] = (int) $value;
            }
            $user = sindesg_user();
            if ($user === null) {
                throw new InvalidArgumentException('Su sesión de usuario no está activa.');
            }
            $preview = $session['report'];
            $approved = $this->bulk->validateSelectedRows($preview, $rows, $user);
            $approvedRows = array_fill_keys(array_keys($approved), true);
            $preview['issues'] = array_values(array_filter(
                (array) $preview['issues'],
                static fn (array $issue): bool => !isset($approvedRows[(int) ($issue['excel_row'] ?? 0)])
            ));
            foreach ($preview['rows'] as &$line) {
                if (isset($approvedRows[(int) ($line['excel_row'] ?? 0)])) {
                    $line['status'] = 'validated_manually';
                    $line['reason'] = 'Confirmación colectiva del P4 · Evidencia #'
                        . $approved[(int) $line['excel_row']];
                }
            }
            unset($line);
            $preview['valid_count'] += count($approved);
            $preview['blocked_count'] -= count($approved);
            // Conservar la referencia privada al MISMO Excel tras validar.
            $session['report'] = $preview;
            $_SESSION['p4_nominal_review'] = $session;
            $page = $this->handleGet('show', ['id' => $requestId]);
            $page['data']['nominalPreview'] = $preview;
            $page['data']['nominalPreviewSuccess'] = count($approved)
                . ' incidencias validadas en una sola operación. '
                . 'Decisiones individuales auditadas; sin importar funcionarios ni mover inventario.';
            return $page;
        }

        if ($action === 'validate-nominal-row') {
            if (!sindesg_can('requests.manage')) {
                throw new InvalidArgumentException('No tiene permiso para validar filas nominales P4.');
            }
            $requestId = (int) ($post['request_id'] ?? 0);
            $session = $_SESSION['p4_nominal_review'] ?? null;
            if (!is_array($session)
                || (int) ($session['at'] ?? 0) < time() - 3600
                || !is_array($session['report'] ?? null)
                || (int) ($session['report']['request_id'] ?? 0) !== $requestId
                || (int) ($session['user_id'] ?? 0) !== (int) (sindesg_user()['id'] ?? 0)) {
                throw new InvalidArgumentException('La prevalidación venció. Adjunte de nuevo el Excel.');
            }
            if ((string) ($post['confirm_identity'] ?? '') !== '1') {
                throw new InvalidArgumentException('El P4 debe confirmar que asume la validación de identidad indicada en RRHH.');
            }
            $preview = $session['report'];
            $row = (int) ($post['excel_row'] ?? 0);
            $user = sindesg_user();
            if ($user === null) {
                throw new InvalidArgumentException('Su sesión de usuario no está activa.');
            }
            $evidenceId = $this->bulk->validatePendingRow(
                $preview,
                $row,
                (int) ($post['employee_id'] ?? 0),
                (string) ($post['justification'] ?? ''),
                $user
            );
            // Actualizar solamente el informe temporal mostrado. El
            // próximo Excel se revalida COMPLETO contra RRHH y evidencias.
            foreach ($preview['issues'] as $index => $issue) {
                if ((int) ($issue['excel_row'] ?? 0) === $row
                    && (string) ($issue['severity'] ?? '') === 'blocking') {
                    unset($preview['issues'][$index]);
                    $preview['valid_count']++;
                    $preview['blocked_count']--;
                    break;
                }
            }
            $preview['issues'] = array_values($preview['issues']);
            foreach ($preview['rows'] as &$line) {
                if ((int) ($line['excel_row'] ?? 0) === $row) {
                    $line['status'] = 'validated_manually';
                    $line['reason'] = 'Confirmado por P4 · Evidencia #' . $evidenceId;
                    break;
                }
            }
            unset($line);
            // Conservar la referencia privada al MISMO Excel tras validar.
            $session['report'] = $preview;
            $_SESSION['p4_nominal_review'] = $session;
            $page = $this->handleGet('show', ['id' => $requestId]);
            $page['data']['nominalPreview'] = $preview;
            $page['data']['nominalPreviewSuccess'] =
                'Fila ' . $row . ' validada con evidencia #' . $evidenceId
                . '. No se importó ni entregó ninguna unidad.';
            return $page;
        }

        if ($action === 'import-from-nominal-preview') {
            if (!sindesg_can('requests.manage')) {
                throw new InvalidArgumentException('No tiene permiso para cargar la conciliación P4.');
            }
            $requestId = (int) ($post['request_id'] ?? 0);
            $session = $_SESSION['p4_nominal_review'] ?? null;
            $user = sindesg_user();
            if (!is_array($session) || $user === null
                || (int) ($session['user_id'] ?? 0) !== (int) ($user['id'] ?? 0)
                || (int) ($session['at'] ?? 0) < time() - P4NominalStagedFile::TTL
                || (int) ($session['report']['request_id'] ?? 0) !== $requestId
                || !is_array($session['file'] ?? null)) {
                throw new InvalidArgumentException(
                    'La conciliación venció. Realice una nueva prevalidación con el Excel.'
                );
            }
            $report = $session['report'];
            $file = $session['file'];
            $sourcePath = P4NominalStagedFile::resolve(
                $file, (string) ($report['source_sha256'] ?? '')
            );
            if ((int) ($report['product_id'] ?? 0) <= 0
                || (int) ($post['product_id'] ?? 0) !== (int) $report['product_id']) {
                throw new InvalidArgumentException('Producto distinto del Excel conciliado.');
            }
            // Releer todo el XLSX y RRHH: NO usar contadores ni identidades
            // potencialmente obsoletas de la sesión como prueba de importación.
            $result = $this->bulk->importWorkbook(
                $requestId,
                (int) $report['product_id'],
                $sourcePath,
                (string) $file['extension'],
                (string) $file['filename'],
                (string) ($post['allow_partial'] ?? '') === '1',
                (string) ($post['accept_document_gap'] ?? '') === '1',
                $user
            );
            P4NominalStagedFile::delete($file);
            unset($_SESSION['p4_nominal_review']);
            flash('success', 'Cargados ' . $result['added']
                . ' funcionarios al borrador, desde el Excel ya conciliado. '
                . $result['skipped'] . ' pendientes; cero movimientos de inventario.'
                . ((int) ($result['document_gap'] ?? 0) > 0
                    ? ' Diferencia declarada de ' . (int) $result['document_gap']
                      . ' unidad(es) registrada como pendiente documental.'
                    : ''));
            $this->redirect('?module=requests&action=show&id=' . $requestId);
        }

        if ($action === 'import-nominal-file' || $action === 'preview-nominal-file') {
            $requestId = (int) ($post['request_id'] ?? 0);
            $file = $_FILES['nominal_workbook'] ?? null;
            if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Adjunte un XLSX o CSV válido.');
            }
            $path = (string) ($file['tmp_name'] ?? '');
            $filename = basename((string) ($file['name'] ?? ''));
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $bytes = (int) ($file['size'] ?? 0);
            if ($bytes <= 0 || $bytes > 15 * 1024 * 1024 || !in_array($ext, ['xlsx', 'csv'], true)) {
                throw new InvalidArgumentException('Solo se admiten archivos XLSX/CSV de hasta 15 MB.');
            }
            if ($path === '' || !is_uploaded_file($path)) {
                throw new InvalidArgumentException('La carga no es un archivo HTTP válido.');
            }
            if ($action === 'preview-nominal-file') {
                $report = $this->bulk->previewWorkbook(
                    $requestId,
                    (int) ($post['product_id'] ?? 0),
                    $path,
                    $ext,
                    $filename
                );
                // Se conserva una copia temporal PRIVADA (fuera de htdocs)
                // durante máximo una hora. La prevalidación no escribe en BD.
                // No aceptar rutas recibidas del navegador.
                $savedFile = P4NominalStagedFile::stage($path, $filename);
                if (!hash_equals(
                    (string) ($report['source_sha256'] ?? ''),
                    $savedFile['sha256']
                )) {
                    P4NominalStagedFile::delete($savedFile);
                    throw new RuntimeException('El archivo cambió durante su prevalidación.');
                }
                $previous = $_SESSION['p4_nominal_review']['file'] ?? null;
                $_SESSION['p4_nominal_review'] = [
                    'at' => time(),
                    'user_id' => (int) (sindesg_user()['id'] ?? 0),
                    'report' => $report,
                    'file' => $savedFile,
                ];
                if (is_array($previous)) {
                    P4NominalStagedFile::delete($previous);
                }
                $page = $this->handleGet('show', ['id' => $requestId]);
                $page['data']['nominalPreview'] = $report;
                return $page;
            }

            $report = $this->bulk->importWorkbook(
                $requestId,
                (int) ($post['product_id'] ?? 0),
                $path,
                $ext,
                $filename,
                (string) ($post['allow_partial'] ?? '') === '1',
                (string) ($post['accept_document_gap'] ?? '') === '1',
                sindesg_user()
            );
            flash('success', 'Requisición ' . $report['requisition'] . ': '
                . $report['added'] . ' asignaciones agregadas a borrador; '
                . $report['skipped'] . ' pendientes de corrección. Ningún movimiento de inventario.'
                . ((int) ($report['document_gap'] ?? 0) > 0
                    ? ' Diferencia documental aceptada y auditada: '
                      . (int) $report['document_gap'] . ' unidad(es) SIN destinatario ni stock.'
                    : ''));
            if ($report['issues'] !== []) {
                flash('error', 'Observaciones: ' . implode(' | ', array_slice($report['issues'], 0, 8)));
            }
            $this->redirect('?module=requests&action=show&id=' . $requestId);
        }

        if ($action === 'preview-boots') {
            $file = $_FILES['boots_workbook'] ?? null;
            if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Adjunte un Excel XLSX o CSV válido para revisar.');
            }
            $fileSize = (int) ($file['size'] ?? 0);
            $path = (string) ($file['tmp_name'] ?? '');
            $fileName = basename((string) ($file['name'] ?? ''));
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($fileSize <= 0 || $fileSize > 15 * 1024 * 1024) {
                throw new InvalidArgumentException('El listado debe tener entre 1 byte y 15 MB.');
            }
            if (!in_array($extension, ['xlsx', 'csv'], true)) {
                throw new InvalidArgumentException('Únicamente se admiten XLSX o CSV.');
            }
            if ($path === '' || !is_uploaded_file($path)) {
                throw new InvalidArgumentException('El archivo no procede de una carga HTTP válida.');
            }

            $sheets = (new SpreadsheetReader())->read($path, $extension);
            $report = (new BootsP4WorkbookPreview())->analyze($sheets, $fileName);

            return [
                'view' => SINDESG_ROOT . '/app/Views/requests/boots-import.php',
                'title' => 'Prevalidación de dotación P4',
                'data' => ['bootsPreview' => $report],
            ];
        }

        if ($action === 'save') {
            $requestId = $this->service->createDraft($post);
            flash('success', 'Solicitud creada en borrador. Ahora agregue los artículos requeridos.');
            $this->redirect('?module=requests&action=show&id=' . $requestId);
        }

        if ($action === 'add-item') {
            $requestId = (int) ($post['request_id'] ?? 0);
            $this->service->addItem($requestId, $post);
            flash('success', 'Renglón agregado a la solicitud.');
            $this->redirect('?module=requests&action=show&id=' . $requestId);
        }

        if ($action === 'remove-item') {
            $requestId = (int) ($post['request_id'] ?? 0);
            $this->service->removeItem($requestId, (int) ($post['item_id'] ?? 0));
            flash('success', 'Renglón eliminado del borrador.');
            $this->redirect('?module=requests&action=show&id=' . $requestId);
        }

        if ($action === 'submit') {
            $requestId = (int) ($post['request_id'] ?? 0);
            $this->service->submit($requestId);
            flash('success', 'Solicitud enviada al Almacén Central para revisión.');
            $this->redirect('?module=requests&action=show&id=' . $requestId);
        }

        if ($action === 'start-review') {
            $requestId = (int) ($post['request_id'] ?? 0);
            $this->service->startReview($requestId);
            flash('success', 'Solicitud marcada como en revisión.');
            $this->redirect('?module=requests&action=show&id=' . $requestId);
        }

        if ($action === 'decide') {
            $requestId = (int) ($post['request_id'] ?? 0);
            $status = $this->service->decide($requestId, $post);
            $labels = [
                'approved' => 'Solicitud aprobada totalmente.',
                'partially_approved' => 'Solicitud aprobada parcialmente.',
                'rejected' => 'Solicitud rechazada.',
            ];
            flash('success', $labels[$status] ?? 'Decisión registrada.');
            $this->redirect('?module=requests&action=show&id=' . $requestId);
        }

        throw new InvalidArgumentException('La operación solicitada no es válida.');
    }

    /** @param list<array<string,mixed>> $items
     *  @return array<string,float>
     */
    private function requestProgressQuantities(int $requestId, array $items): array
    {
        $quantities = $this->repository->commitmentEvidence($requestId);
        $quantities['delivered'] = array_sum(array_map(
            static fn (array $row): float => (float) ($row['quantity_delivered'] ?? 0),
            $items
        ));
        return $quantities;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
