<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DeliveryRepository;
use App\Services\DeliveryService;
use App\Services\RrhhEmployeeDirectory;
use App\Support\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class DeliveryController
{
    private DeliveryRepository $repository;
    private DeliveryService $service;
    private RrhhEmployeeDirectory $employees;

    public function __construct(private Database $database)
    {
        $this->repository = new DeliveryRepository($database->connection('sindesg'));
        $this->employees = new RrhhEmployeeDirectory($database);
        $this->service = new DeliveryService($this->repository, $this->employees);
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

            $deliveryId = (int) ($post['delivery_id'] ?? $query['id'] ?? 0);
            if ($deliveryId > 0) {
                $this->redirect('?module=deliveries&action=show&id=' . $deliveryId);
            }

            $requestItemId = (int) ($post['request_item_id'] ?? $query['request_item_id'] ?? 0);
            $this->redirect(
                $requestItemId > 0
                    ? '?module=deliveries&action=new&request_item_id=' . $requestItemId
                    : '?module=deliveries&action=new'
            );
        } catch (Throwable $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/deliveries/unavailable.php',
                'title' => 'Despacho individual',
                'data' => [
                    'deliveryError' => 'El módulo de Despacho individual aún no está disponible en esta instalación. Aplique la migración 012_individual_deliveries.sql y vuelva a intentarlo.',
                ],
            ];
        }
    }

    private function handleGet(string $action, array $query): array
    {
        if ($action === 'new') {
            $requestItemId = (int) ($query['request_item_id'] ?? 0);
            $pendingLine = $requestItemId > 0
                ? $this->repository->pendingNominalLine($requestItemId)
                : null;

            if ($requestItemId > 0 && $pendingLine === null) {
                throw new InvalidArgumentException(
                    'La entrega nominal seleccionada ya no tiene cantidades pendientes.'
                );
            }

            $recipientOutOfScope = false;
            $pendingRecipientCurrent = null;

            if ($pendingLine !== null) {
                $pendingRecipientCurrent = $this->employees->findEligibleEmployee(
                    (int) $pendingLine['beneficiary_employee_id']
                );
                $p4UnitId = $this->repository->p4OrganizationUnitId(
                    (int) $pendingLine['p4_warehouse_id']
                );

                $recipientOutOfScope = $p4UnitId !== null
                    && (
                        $pendingRecipientCurrent === null
                        || !$this->employees->belongsToOrganizationUnit(
                            (int) $pendingLine['beneficiary_employee_id'],
                            $p4UnitId
                        )
                    );
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/deliveries/form.php',
                'title' => 'Nuevo despacho individual',
                'data' => [
                    'activeP4s' => $this->repository->activeP4s(),
                    'pendingLine' => $pendingLine,
                    'recipientOutOfScope' => $recipientOutOfScope,
                    'pendingRecipientCurrent' => $pendingRecipientCurrent,
                    'priorHistory' => $pendingLine
                        ? $this->repository->historySummaryForEmployee(
                            (int) $pendingLine['beneficiary_employee_id'],
                            10
                        )
                        : [],
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                    'historyApiUrl' => sindesg_url('public/api/delivery-history.php'),
                    'defaultDeliveredAt' => date('Y-m-d\TH:i'),
                ],
            ];
        }

        if ($action === 'show') {
            $deliveryId = (int) ($query['id'] ?? 0);
            $delivery = $this->repository->find($deliveryId);

            if ($delivery === null) {
                throw new InvalidArgumentException('El despacho solicitado no existe.');
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/deliveries/show.php',
                'title' => (string) $delivery['delivery_code'],
                'data' => [
                    'delivery' => $delivery,
                    'deliveryItems' => $this->repository->items($deliveryId),
                    'deliveryVerification' => $delivery['status'] === 'confirmed'
                        ? $this->repository->confirmationEvidence($deliveryId)
                        : [
                            'rows' => [],
                            'expected' => 0,
                            'verified' => 0,
                            'all_verified' => false,
                        ],
                    'p4StockOptions' => $delivery['status'] === 'draft'
                        ? $this->repository->stockOptionsForP4(
                            (int) $delivery['p4_warehouse_id']
                        )
                        : [],
                    'recipientHistory' => $this->repository->historyForEmployee(
                        (int) $delivery['recipient_employee_id'],
                        20
                    ),
                ],
            ];
        }

        if ($action === 'history') {
            $employeeId = (int) ($query['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                throw new InvalidArgumentException('Seleccione un funcionario.');
            }

            $history = $this->repository->historyForEmployee($employeeId, 200);
            $employee = $this->employees->findEligibleEmployee($employeeId);

            return [
                'view' => SINDESG_ROOT . '/app/Views/deliveries/history.php',
                'title' => 'Historial de dotación',
                'data' => [
                    'historyEmployee' => $employee,
                    'deliveryHistory' => $history,
                    'historyEmployeeId' => $employeeId,
                ],
            ];
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/deliveries/index.php',
            'title' => 'Despacho individual',
            'data' => [
                'deliveryStats' => $this->repository->stats(),
                'pendingNominalDeliveries' => $this->repository->pendingNominal(40),
                'deliveries' => $this->repository->recent(60),
            ],
        ];
    }

    private function handlePost(string $action, array $post): array
    {
        if ($action === 'save') {
            $deliveryId = $this->service->createDraft($post);
            flash(
                'success',
                'Despacho creado en borrador. Revise los artículos antes de confirmar.'
            );
            $this->redirect('?module=deliveries&action=show&id=' . $deliveryId);
        }

        if ($action === 'add-item') {
            $deliveryId = (int) ($post['delivery_id'] ?? 0);
            $this->service->addItem($deliveryId, $post);
            flash('success', 'Artículo agregado al despacho.');
            $this->redirect('?module=deliveries&action=show&id=' . $deliveryId);
        }

        if ($action === 'remove-item') {
            $deliveryId = (int) ($post['delivery_id'] ?? 0);
            $this->service->removeItem(
                $deliveryId,
                (int) ($post['item_id'] ?? 0)
            );
            flash('success', 'Artículo retirado del borrador.');
            $this->redirect('?module=deliveries&action=show&id=' . $deliveryId);
        }

        if ($action === 'confirm') {
            $deliveryId = (int) ($post['delivery_id'] ?? 0);
            $result = $this->service->confirm($deliveryId);
            flash(
                'success',
                !empty($result['already_confirmed'])
                    ? 'El despacho ya estaba confirmado.'
                    : 'Despacho confirmado. El inventario del P4 fue actualizado.'
            );
            $this->redirect('?module=deliveries&action=show&id=' . $deliveryId);
        }

        if ($action === 'cancel') {
            $deliveryId = (int) ($post['delivery_id'] ?? 0);
            $this->service->cancel($deliveryId);
            flash('success', 'Despacho cancelado sin alterar inventario.');
            $this->redirect('?module=deliveries&action=show&id=' . $deliveryId);
        }

        throw new InvalidArgumentException('La operación solicitada no es válida.');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
