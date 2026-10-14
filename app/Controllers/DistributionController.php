<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DistributionRepository;
use App\Services\DistributionService;
use App\Services\RrhhEmployeeDirectory;
use App\Support\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class DistributionController
{
    private DistributionRepository $repository;
    private DistributionService $service;

    public function __construct(Database $database)
    {
        $this->repository = new DistributionRepository($database->connection('sindesg'));
        $this->service = new DistributionService(
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
            $distributionId = (int) ($post['distribution_id'] ?? 0);
            $requestId = (int) ($post['request_id'] ?? $query['request_id'] ?? 0);

            if ($distributionId > 0) {
                $this->redirect('?module=distribution&action=show&id=' . $distributionId);
            }

            if ($requestId > 0) {
                $this->redirect('?module=distribution&action=new&request_id=' . $requestId);
            }

            $this->redirect('?module=distribution');
        } catch (Throwable $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/distribution/unavailable.php',
                'title' => 'Distribución a P4',
                'data' => [
                    'distributionError' => 'El módulo de Distribución aún no está disponible en esta instalación. Aplique la migración 010_distribution.sql y vuelva a intentarlo.',
                ],
            ];
        }
    }

    private function handleGet(string $action, array $query): array
    {
        if ($action === 'new') {
            $requestId = (int) ($query['request_id'] ?? 0);
            $request = $this->repository->requestForPreparation($requestId);

            if ($request === null) {
                throw new InvalidArgumentException('La solicitud seleccionada no está aprobada o no existe.');
            }

            if ((int) $request['p4_is_active'] !== 1) {
                throw new InvalidArgumentException('El P4 de la solicitud está inactivo.');
            }

            if ($this->repository->hasOpenDistribution($requestId)) {
                throw new InvalidArgumentException('Esta solicitud ya tiene una transferencia abierta.');
            }

            $items = $this->repository->requestItemsForPreparation($requestId);
            if ($items === []) {
                throw new InvalidArgumentException('La solicitud no tiene cantidades aprobadas pendientes de distribución.');
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/distribution/form.php',
                'title' => 'Preparar distribución',
                'data' => [
                    'distributionRequest' => $request,
                    'distributionRequestItems' => $items,
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                ],
            ];
        }

        if ($action === 'show') {
            $distributionId = (int) ($query['id'] ?? 0);
            $distribution = $this->repository->find($distributionId);

            if ($distribution === null) {
                throw new InvalidArgumentException('La transferencia solicitada no existe.');
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/distribution/show.php',
                'title' => (string) $distribution['transfer_code'],
                'data' => [
                    'distribution' => $distribution,
                    'distributionItems' => $this->repository->items($distributionId),
                    'distributionVerification' => $this->repository->movementEvidence($distributionId),
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                ],
            ];
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/distribution/index.php',
            'title' => 'Distribución a P4',
            'data' => [
                'distributionStats' => $this->repository->stats(),
                'distributionStatsRestricted' => !\App\Services\P4WarehouseScope::current($this->database)['unrestricted'],
                'readyRequests' => \App\Services\P4WarehouseScope::current($this->database)['unrestricted'] ? $this->repository->readyRequests() : [],
                'distributions' => $this->repository->recent(100, \App\Services\P4WarehouseScope::current($this->database)['unrestricted'] ? null : \App\Services\P4WarehouseScope::current($this->database)['warehouses']),
            ],
        ];
    }

    private function handlePost(string $action, array $post): array
    {
        if ($action === 'prepare') {
            $requestId = (int) ($post['request_id'] ?? 0);
            $distributionId = $this->service->prepare($requestId, $post);
            flash('success', 'Transferencia preparada. Revise el detalle antes de autorizar.');
            $this->redirect('?module=distribution&action=show&id=' . $distributionId);
        }

        if ($action === 'authorize') {
            $distributionId = (int) ($post['distribution_id'] ?? 0);
            $this->service->authorize($distributionId, $post);
            flash('success', 'Transferencia autorizada. El stock será revalidado al despachar.');
            $this->redirect('?module=distribution&action=show&id=' . $distributionId);
        }

        if ($action === 'dispatch') {
            $distributionId = (int) ($post['distribution_id'] ?? 0);
            $this->service->dispatch($distributionId, $post);
            flash('success', 'Transferencia despachada. El inventario pasó del Almacén Central a Tránsito.');
            $this->redirect('?module=distribution&action=show&id=' . $distributionId);
        }

        if ($action === 'receive') {
            $distributionId = (int) ($post['distribution_id'] ?? 0);
            $this->service->receive($distributionId, $post);
            flash('success', 'Transferencia recibida. El inventario ingresó al P4.');
            $this->redirect('?module=distribution&action=show&id=' . $distributionId);
        }

        if ($action === 'cancel') {
            $distributionId = (int) ($post['distribution_id'] ?? 0);
            $this->service->cancel($distributionId);
            flash('success', 'Transferencia cancelada sin alterar inventario.');
            $this->redirect('?module=distribution&action=show&id=' . $distributionId);
        }

        throw new InvalidArgumentException('La operación solicitada no es válida.');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
