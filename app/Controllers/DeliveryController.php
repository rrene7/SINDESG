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
            $user = (array) sindesg_user();
            $roles = (array) ($user['role_codes'] ?? []);
            $p4Only = in_array('P4', $roles, true)
                && !in_array('ADMIN', $roles, true)
                && !in_array('CENTRAL', $roles, true);
            if ($p4Only) {
                $guard = new \App\Services\P4DeliveryGuard($this->database->connection('sindesg'));
                $userId = (int) ($user['id'] ?? 0);
                $isPost = strtoupper($method) === 'POST';
                $allowed = $isPost ? ['save', 'confirm'] : ['index', 'new', 'show'];
                if (!in_array($action, $allowed, true)) {
                    throw new InvalidArgumentException('Operación no habilitada para este P4.');
                }
                if ($isPost && $action === 'save') {
                    $itemId = (int) ($post['request_item_id'] ?? 0);
                    $line = $guard->assertNominal($userId, $itemId);
                    $post['p4_warehouse_id'] = (int) $line['p4_warehouse_id'];
                    $post['delivered_by_employee_id'] = (int) ($user['employee_id'] ?? 0);
                    // Never allow P4 to bypass the recipient's active RRHH assignment.
                    $post['allow_out_of_scope'] = '0';
                    $post['scope_override_reason'] = '';
                } elseif ($isPost || $action === 'show') {
                    $id = (int) ($isPost ? ($post['delivery_id'] ?? 0) : ($query['id'] ?? 0));
                    $guard->assertDelivery($userId, $id);
                } elseif ($action === 'new') {
                    $guard->assertNominal($userId, (int) ($query['request_item_id'] ?? 0));
                }
            }
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
            if ($this->isP4Only()) {
                // Reject direct foreign-warehouse URLs without redirect loops.
                $this->redirect('?module=deliveries');
            }


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
                    'activeP4s' => $this->isP4Only() ? [] : $this->repository->activeP4s(),
                    'pendingLine' => $pendingLine,
                    'recipientOutOfScope' => $recipientOutOfScope,
                    'pendingRecipientCurrent' => $pendingRecipientCurrent,
                    'priorHistory' => $this->isP4Only()
                        ? []
                        : ($pendingLine
                            ? $this->repository->historySummaryForEmployee(
                                (int) $pendingLine['beneficiary_employee_id'], 10
                            ) : []),
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
                    'recipientHistory' => $this->isP4Only()
                        ? []
                        : $this->repository->historyForEmployee(
                            (int) $delivery['recipient_employee_id'], 20
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

        if ($this->isP4Only()) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/deliveries/p4-index.php',
                'title' => 'Entregas de mi P4',
                'data' => [
                    'nominalQueue' => $this->repository->pendingNominalForUser(
                        (int) (sindesg_user()['id'] ?? 0)
                    ),
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
            $evidence = null;
            if ($this->isP4Only()) {
                $raw = (string) ($post['recipient_signature_png'] ?? '');
                if (!str_starts_with($raw, 'data:image/png;base64,') || strlen($raw) > 220000) {
                    throw new InvalidArgumentException('La firma de recibido es obligatoria y debe ser una imagen PNG válida.');
                }
                $png = base64_decode(substr($raw, strlen('data:image/png;base64,')), true);
                if ($png === false || strlen($png) < 100 || strlen($png) > 160000
                    || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
                    throw new InvalidArgumentException('Firma inválida. Capture nuevamente el recibido.');
                }
                $actorId = (int) (sindesg_user()['id'] ?? 0);
                if ($actorId <= 0) throw new InvalidArgumentException('Sesión inválida.');
                $evidence = ['png' => $png, 'actor' => $actorId];
            }
            $result = $this->service->confirm($deliveryId, $evidence);
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

    private function isP4Only(): bool
    {
        $roles = (array) (sindesg_user()['role_codes'] ?? []);
        return in_array('P4', $roles, true)
            && !in_array('ADMIN', $roles, true)
            && !in_array('CENTRAL', $roles, true);
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
