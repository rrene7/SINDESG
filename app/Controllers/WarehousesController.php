<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\RrhhRepository;
use App\Repositories\WarehouseRepository;
use App\Services\RrhhEmployeeDirectory;
use App\Services\WarehouseService;
use App\Support\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class WarehousesController
{
    private WarehouseRepository $warehouses;
    private RrhhRepository $rrhh;
    private RrhhEmployeeDirectory $employees;
    private WarehouseService $service;

    public function __construct(private Database $database)
    {
        $this->warehouses = new WarehouseRepository($database->connection('sindesg'));
        $this->rrhh = new RrhhRepository($database);
        $this->employees = new RrhhEmployeeDirectory($database);
        $this->service = new WarehouseService(
            $this->warehouses,
            $this->rrhh,
            $this->employees
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
            $this->redirect($action === 'toggle'
                ? '?module=warehouses&action=show&id=' . (int) ($post['warehouse_id'] ?? 0)
                : '?module=warehouses&action=new');
        } catch (Throwable $exception) {
            return [
                'view' => SINDESG_ROOT . '/app/Views/warehouses/unavailable.php',
                'title' => 'Almacenes y P4',
                'data' => [
                    'warehouseError' => 'El módulo de Almacenes y P4 aún no está disponible en esta instalación. Aplique la migración 008_warehouses_p4.sql y vuelva a intentarlo.',
                ],
            ];
        }
    }

    private function handleGet(string $action, array $query): array
    {
        if ($action === 'new') {
            return [
                'view' => SINDESG_ROOT . '/app/Views/warehouses/form.php',
                'title' => 'Nuevo P4',
                'data' => [
                    'unitSearchUrl' => sindesg_url('public/api/units.php?scope=p4'),
                    'employeeSearchUrl' => sindesg_url('public/api/employees.php'),
                    'rrhhAvailable' => $this->database->health('rrhh')['ok'],
                ],
            ];
        }

        if ($action === 'show') {
            $warehouseId = (int) ($query['id'] ?? 0);
            $warehouse = $this->warehouses->find($warehouseId);

            if ($warehouse === null) {
                throw new InvalidArgumentException('El almacén solicitado no existe.');
            }

            $currentUnit = null;
            $currentResponsible = null;

            try {
                if (!empty($warehouse['organization_unit_id'])) {
                    $currentUnit = $this->rrhh->findUnit((int) $warehouse['organization_unit_id']);
                }
                if (!empty($warehouse['responsible_employee_id'])) {
                    $currentResponsible = $this->rrhh->findPerson((int) $warehouse['responsible_employee_id']);
                }
            } catch (Throwable $exception) {
                $currentUnit = null;
                $currentResponsible = null;
            }

            return [
                'view' => SINDESG_ROOT . '/app/Views/warehouses/show.php',
                'title' => (string) $warehouse['name'],
                'data' => [
                    'warehouse' => $warehouse,
                    'warehouseStock' => $this->warehouses->stock($warehouseId),
                    'p4RequestTracking' => $warehouse['warehouse_type'] === 'P4'
                        ? $this->warehouses->requestsForP4($warehouseId)
                        : [],
                    'currentUnit' => $currentUnit,
                    'currentResponsible' => $currentResponsible,
                ],
            ];
        }

        $warehouses = \App\Services\P4WarehouseScope::filter($this->warehouses->all(), \App\Services\P4WarehouseScope::current($this->database), 'id');
        $unitIds = [];
        foreach ($warehouses as $warehouse) {
            if (!empty($warehouse['organization_unit_id'])) {
                $unitIds[] = (int) $warehouse['organization_unit_id'];
            }
        }

        $currentUnits = [];
        try {
            $currentUnits = $this->rrhh->unitsByIds($unitIds);
        } catch (Throwable $exception) {
            $currentUnits = [];
        }

        return [
            'view' => SINDESG_ROOT . '/app/Views/warehouses/index.php',
            'title' => 'Almacenes y P4',
            'data' => [
                'warehouseStats' => $this->warehouses->stats(),
                'warehouseStatsRestricted' => !\App\Services\P4WarehouseScope::current($this->database)['unrestricted'],
                'warehouses' => $warehouses,
                'currentUnits' => $currentUnits,
                'rrhhAvailable' => $this->database->health('rrhh')['ok'],
            ],
        ];
    }

    private function handlePost(string $action, array $post): array
    {
        if ($action === 'save') {
            $warehouseId = $this->service->createP4($post);
            flash('success', 'P4 creado y vinculado a la estructura oficial de RRHH2029.');
            $this->redirect('?module=warehouses&action=show&id=' . $warehouseId);
        }

        if ($action === 'toggle') {
            $warehouseId = (int) ($post['warehouse_id'] ?? 0);
            $active = ((string) ($post['active'] ?? '0')) === '1';
            $this->service->setActive($warehouseId, $active);
            flash(
                'success',
                $active
                    ? 'P4 reactivado. Conserva todo su historial y existencias.'
                    : 'P4 inactivado. El historial y las existencias no fueron eliminados.'
            );
            $this->redirect('?module=warehouses&action=show&id=' . $warehouseId);
        }

        throw new InvalidArgumentException('La operación solicitada no es válida.');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . sindesg_url($path), true, 303);
        exit;
    }
}
