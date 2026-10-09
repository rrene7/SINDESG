<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CatalogRepository;
use App\Repositories\ReceiptRepository;
use App\Services\ReceiptService;
use App\Services\RrhhEmployeeDirectory;
use App\Support\Database;
use InvalidArgumentException;
use PDO;
use Throwable;

final class ReceiptsController
{
    private ReceiptRepository $repository;
    private ReceiptService $service;
    private CatalogRepository $catalog;

    public function __construct(PDO $pdo, Database $database)
    {
        $this->repository = new ReceiptRepository($pdo);
        $this->service = new ReceiptService(
            $this->repository,
            new RrhhEmployeeDirectory($database)
        );
        $this->catalog = new CatalogRepository($pdo);
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
                    throw new InvalidArgumentException(
                        'La sesión del formulario venció. Intente nuevamente.'
                    );
                }

                return $this->handlePost($action, $post);
            }

            return $this->handleGet($action, $query);
        } catch (InvalidArgumentException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect($action === 'confirm'
                ? '?module=receipts&action=show&id=' . (int) ($post['receipt_id'] ?? 0)
                : '?module=receipts&action=new');
        } catch (Throwable $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/receipts/unavailable.php',
                'title' => 'Recepción de lotes',
                'data' => [
                    'receiptError' => 'El módulo de recepción aún no está disponible en esta instalación. Aplique las migraciones pendientes, incluida 007_dashboard_rrhh_receipts.sql, y vuelva a intentarlo.',
                ],
            ];
        }
    }

    private function handleGet(string $action, array $query): array
    {
        if ($action === 'new') {
            return [
                'view' => SINDESG_ROOT . '/app/Views/receipts/form.php',
                'title' => 'Nueva recepción',
                'data' => [
                    'variantOptions' => $this->catalog->listVariantOptions(),
                    'sourceTypes' => ReceiptService::sourceTypes(),
                    'defaultReceivedAt' => date('Y-m-d\TH:i'),
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                ],
            ];
        }

        if ($action === 'show') {
            $receiptId = (int) ($query['id'] ?? 0);
            $receipt = $this->repository->find($receiptId);

            if ($receipt === null) {
                throw new InvalidArgumentException('La recepción solicitada no existe.');
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/receipts/show.php',
                'title' => (string) $receipt['receipt_code'],
                'data' => [
                    'receipt' => $receipt,
                    'receiptItems' => $this->repository->items($receiptId),
                    'receiptVerification' => $receipt['status'] === 'confirmed'
                        ? $this->repository->confirmationEvidence($receiptId)
                        : [
                            'rows' => [],
                            'expected' => 0,
                            'matched' => 0,
                            'all_verified' => false,
                        ],
                    'sourceTypes' => ReceiptService::sourceTypes(),
                ],
            ];
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/receipts/index.php',
            'title' => 'Recepción de lotes',
            'data' => [
                'receiptStats' => $this->repository->stats(),
                'receipts' => $this->repository->recent(50),
                'sourceTypes' => ReceiptService::sourceTypes(),
            ],
        ];
    }

    private function handlePost(string $action, array $post): array
    {
        if ($action === 'save-draft') {
            $receiptId = $this->service->createDraft($post);
            flash(
                'success',
                'Recepción guardada en borrador. Revise el detalle antes de confirmar el ingreso al inventario.'
            );
            $this->redirect('?module=receipts&action=show&id=' . $receiptId);
        }

        if ($action === 'confirm') {
            $receiptId = (int) ($post['receipt_id'] ?? 0);
            $result = $this->service->confirm($receiptId);

            flash(
                $result['already_confirmed'] ? 'warning' : 'success',
                $result['already_confirmed']
                    ? 'La recepción ya estaba confirmada; no se generaron movimientos duplicados.'
                    : 'Recepción confirmada. El lote ingresó al Almacén Central y el kardex fue actualizado.'
            );

            $this->redirect('?module=receipts&action=show&id=' . $receiptId);
        }

        throw new InvalidArgumentException('La operación solicitada no es válida.');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
