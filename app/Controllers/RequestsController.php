<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\RequestRepository;
use App\Services\RequestService;
use App\Services\SpreadsheetReader;
use App\Services\BootsP4WorkbookPreview;
use App\Services\RrhhEmployeeDirectory;
use App\Support\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class RequestsController
{
    private RequestRepository $repository;
    private RequestService $service;

    public function __construct(private Database $database)
    {
        $this->repository = new RequestRepository($database->connection('sindesg'));
        $this->service = new RequestService(
            $this->repository,
            new RrhhEmployeeDirectory($database)
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
