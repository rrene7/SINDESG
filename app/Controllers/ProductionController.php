<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ProductionRepository;
use App\Services\ProductionService;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class ProductionController
{
    private ProductionRepository $repository;
    private ProductionService $service;

    public function __construct(PDO $pdo)
    {
        $this->repository = new ProductionRepository($pdo);
        $this->service = new ProductionService($this->repository);
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

            $this->redirect($action === 'confirm'
                ? '?module=production&action=show&id=' . (int) ($post['production_order_id'] ?? 0)
                : '?module=production&action=new');
        } catch (Throwable $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/production/unavailable.php',
                'title' => 'Producción interna',
                'data' => [
                    'productionError' => 'El módulo de producción aún no está disponible en esta instalación. Aplique la migración 006_production.sql y vuelva a intentarlo.',
                ],
            ];
        }
    }

    private function handleGet(string $action, array $query): array
    {
        if ($action === 'new') {
            return [
                'view' => SINDESG_ROOT . '/app/Views/production/form.php',
                'title' => 'Nueva orden de producción',
                'data' => [
                    'variantOptions' => $this->repository->variantOptions(),
                    'defaultProducedAt' => date('Y-m-d\TH:i'),
                ],
            ];
        }

        if ($action === 'show') {
            $orderId = (int) ($query['id'] ?? 0);
            $order = $this->repository->find($orderId);

            if ($order === null) {
                throw new InvalidArgumentException('La orden de producción solicitada no existe.');
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/production/show.php',
                'title' => (string) $order['production_code'],
                'data' => [
                    'productionOrder' => $order,
                    'productionOutputs' => $this->repository->outputs($orderId),
                    'productionInputs' => $this->repository->inputs($orderId),
                ],
            ];
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/production/index.php',
            'title' => 'Producción interna',
            'data' => [
                'productionStats' => $this->repository->stats(),
                'productionOrders' => $this->repository->recent(50),
            ],
        ];
    }

    private function handlePost(string $action, array $post): array
    {
        if ($action === 'save-draft') {
            $orderId = $this->service->createDraft($post);
            flash(
                'success',
                'Orden guardada en borrador. Revise productos e insumos antes de confirmar.'
            );
            $this->redirect('?module=production&action=show&id=' . $orderId);
        }

        if ($action === 'confirm') {
            $orderId = (int) ($post['production_order_id'] ?? 0);
            $result = $this->service->confirm($orderId);

            flash(
                $result['already_confirmed'] ? 'warning' : 'success',
                $result['already_confirmed']
                    ? 'La orden ya estaba confirmada; no se generaron movimientos duplicados.'
                    : 'Producción confirmada. Los insumos fueron descontados y el producto terminado ingresó al Almacén Central.'
            );
            $this->redirect('?module=production&action=show&id=' . $orderId);
        }

        throw new InvalidArgumentException('La operación solicitada no es válida.');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
