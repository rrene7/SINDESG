<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AdjustmentRepository;
use App\Services\AdjustmentService;
use App\Services\RrhhEmployeeDirectory;
use App\Support\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class AdjustmentController
{
    private AdjustmentRepository $repository;
    private AdjustmentService $service;

    public function __construct(private Database $database)
    {
        $this->repository = new AdjustmentRepository($database->connection('sindesg'));
        $this->service = new AdjustmentService(
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
                    throw new InvalidArgumentException(
                        'La sesión del formulario venció. Intente nuevamente.'
                    );
                }

                return $this->handlePost($action, $post);
            }

            return $this->handleGet($action, $query);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            flash('error', $exception->getMessage());

            $adjustmentId = (int) ($post['adjustment_id'] ?? $query['id'] ?? 0);
            if ($adjustmentId > 0) {
                $this->redirect('?module=adjustments&action=show&id=' . $adjustmentId);
            }

            $sourceItemId = (int) ($post['source_delivery_item_id'] ?? $query['source_delivery_item_id'] ?? 0);
            $type = trim((string) ($post['adjustment_type'] ?? $query['type'] ?? ''));

            $url = '?module=adjustments&action=new';
            if ($sourceItemId > 0) {
                $url .= '&source_delivery_item_id=' . $sourceItemId;
            } elseif ($type !== '') {
                $url .= '&type=' . rawurlencode($type);
            }

            $this->redirect($url);
        } catch (Throwable $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/adjustments/unavailable.php',
                'title' => 'Ajustes y devoluciones',
                'data' => [
                    'adjustmentError' => 'El módulo de Ajustes aún no está disponible en esta instalación. Aplique la migración 014_inventory_adjustments.sql y vuelva a intentarlo.',
                ],
            ];
        }
    }

    private function handleGet(string $action, array $query): array
    {
        if ($action === 'new') {
            $sourceItemId = (int) ($query['source_delivery_item_id'] ?? 0);
            $sourceDeliveryItem = $sourceItemId > 0
                ? $this->repository->sourceDeliveryItem($sourceItemId)
                : null;

            if ($sourceItemId > 0 && $sourceDeliveryItem === null) {
                throw new InvalidArgumentException(
                    'La entrega de origen no existe o todavía no está confirmada.'
                );
            }

            $type = trim((string) ($query['type'] ?? ''));
            if ($sourceDeliveryItem !== null && !in_array($type, AdjustmentRepository::SOURCE_TYPES, true)) {
                $type = 'return';
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/adjustments/form.php',
                'title' => 'Nueva operación de ajuste',
                'data' => [
                    'sourceDeliveryItem' => $sourceDeliveryItem,
                    'selectedAdjustmentType' => $type,
                    'adjustmentWarehouses' => $this->repository->adjustableWarehouses(),
                    'adjustmentVariants' => $this->repository->activeVariants(),
                    'replacementVariants' => $sourceDeliveryItem
                        ? $this->repository->variantsForProduct((int) $sourceDeliveryItem['product_id'])
                        : [],
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                    'defaultOccurredAt' => date('Y-m-d\TH:i'),
                ],
            ];
        }

        if ($action === 'show') {
            $adjustmentId = (int) ($query['id'] ?? 0);
            $adjustment = $this->repository->find($adjustmentId);

            if ($adjustment === null) {
                throw new InvalidArgumentException('El ajuste solicitado no existe.');
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/adjustments/show.php',
                'title' => (string) $adjustment['adjustment_code'],
                'data' => [
                    'adjustment' => $adjustment,
                    'currentPrimaryStock' => $this->repository->physicalStock(
                        (int) $adjustment['warehouse_id'],
                        (int) $adjustment['product_variant_id']
                    ),
                    'currentReplacementStock' => $adjustment['replacement_variant_id'] !== null
                        ? $this->repository->physicalStock(
                            (int) $adjustment['warehouse_id'],
                            (int) $adjustment['replacement_variant_id']
                        )
                        : null,
                ],
            ];
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/adjustments/index.php',
            'title' => 'Ajustes y devoluciones',
            'data' => [
                'adjustmentStats' => $this->repository->stats(),
                'adjustments' => $this->repository->recent(80),
            ],
        ];
    }

    private function handlePost(string $action, array $post): array
    {
        if ($action === 'save') {
            $id = $this->service->createDraft($post);
            flash('success', 'Operación creada en borrador. Revise el impacto antes de confirmar.');
            $this->redirect('?module=adjustments&action=show&id=' . $id);
        }

        if ($action === 'confirm') {
            $id = (int) ($post['adjustment_id'] ?? 0);
            $result = $this->service->confirm($id);
            flash(
                'success',
                !empty($result['already_confirmed'])
                    ? 'La operación ya estaba confirmada.'
                    : 'Operación confirmada. El kardex fue actualizado sin borrar movimientos anteriores.'
            );
            $this->redirect('?module=adjustments&action=show&id=' . $id);
        }

        if ($action === 'cancel') {
            $id = (int) ($post['adjustment_id'] ?? 0);
            $this->service->cancel($id);
            flash('success', 'Borrador cancelado sin modificar inventario.');
            $this->redirect('?module=adjustments&action=show&id=' . $id);
        }

        throw new InvalidArgumentException('La operación solicitada no es válida.');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
