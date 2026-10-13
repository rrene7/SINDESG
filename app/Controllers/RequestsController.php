<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\RequestRepository;
use App\Services\RequestService;
use App\Services\SpreadsheetReader;
use App\Services\BootsP4WorkbookPreview;
use App\Services\NominalBulkRequestService;
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
        $this->bulk = new NominalBulkRequestService($this->repository, $directory);
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

        if ($action === 'show') {
            $requestId = (int) ($query['id'] ?? 0);
            $request = $this->repository->find($requestId);

            if ($request === null) {
                throw new InvalidArgumentException('La solicitud solicitada no existe.');
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/requests/show.php',
                'title' => (string) $request['request_code'],
                'data' => [
                    'serviceRequest' => $request,
                    'requestItems' => $this->repository->items($requestId),
                    'requestCommitmentEvidence' => $this->repository->commitmentEvidence($requestId),
                    'variantOptions' => $this->repository->variantOptions(),
                    'nominalProducts' => $this->repository->nominalProducts(),
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                ],
            ];
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/requests/index.php',
            'title' => 'Solicitudes',
            'data' => [
                'requestStats' => $this->repository->stats(),
                'serviceRequests' => $this->repository->recent(60),
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

        if ($action === 'import-nominal-file') {
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
            $report = $this->bulk->importWorkbook(
                $requestId,
                (int) ($post['product_id'] ?? 0),
                $path,
                $ext,
                $filename,
                (string) ($post['allow_partial'] ?? '') === '1'
            );
            flash('success', 'Requisición ' . $report['requisition'] . ': '
                . $report['added'] . ' asignaciones agregadas a borrador; '
                . $report['skipped'] . ' pendientes de corrección. Ningún movimiento de inventario.');
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

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
