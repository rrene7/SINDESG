<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\RrhhRepository;
use App\Repositories\WarehouseRepository;
use InvalidArgumentException;

final class WarehouseService
{
    public function __construct(
        private WarehouseRepository $warehouses,
        private RrhhRepository $rrhh,
        private RrhhEmployeeDirectory $employees
    ) {
    }

    public function createP4(array $input): int
    {
        $organizationUnitId = (int) ($input['organization_unit_id'] ?? 0);
        $responsibleEmployeeId = (int) ($input['responsible_employee_id'] ?? 0);
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($organizationUnitId <= 0) {
            throw new InvalidArgumentException('Busque y seleccione la Dirección o Zona que tendrá el P4.');
        }

        if ($responsibleEmployeeId <= 0) {
            throw new InvalidArgumentException('Busque y seleccione el funcionario responsable del P4.');
        }

        $unit = $this->rrhh->findUnit($organizationUnitId);
        if (
            $unit === null
            || (int) ($unit['is_active'] ?? 0) !== 1
            || ((string) ($unit['lifecycle_status'] ?? 'vigente')) !== 'vigente'
        ) {
            throw new InvalidArgumentException('La unidad seleccionada no está vigente en RRHH2029.');
        }

        if (!in_array(
            (string) ($unit['unit_type'] ?? ''),
            RrhhRepository::P4_ELIGIBLE_UNIT_TYPES,
            true
        )) {
            throw new InvalidArgumentException(
                'Solo puede habilitar P4 para una Dirección o Zona Policial oficial. Seleccione el tipo de unidad correcto en RRHH2029.'
            );
        }

        if ($this->warehouses->existsForOrganizationUnit($organizationUnitId)) {
            throw new InvalidArgumentException(
                'Esta unidad ya tiene un almacén/P4 configurado en SINDESG.'
            );
        }

        $responsible = $this->employees->findEligibleEmployee($responsibleEmployeeId);
        if ($responsible === null) {
            throw new InvalidArgumentException(
                'El responsable seleccionado no está disponible como funcionario vigente en RRHH2029.'
            );
        }

        return $this->warehouses->createP4([
            'organization_unit_id' => $organizationUnitId,
            'organization_unit_code_snapshot' => $unit['system_code'] ?: $unit['institutional_code'],
            'organization_unit_name_snapshot' => $unit['official_name'],
            'organization_unit_type_snapshot' => $unit['unit_type'],
            'responsible_employee_id' => (int) $responsible['employee_id'],
            'responsible_label_snapshot' => $responsible['display_name'],
            'responsible_document_snapshot' => $responsible['document_number'],
            'responsible_position_snapshot' => $responsible['position_number'],
            'responsible_rank_snapshot' => $responsible['rank_name'],
            'notes' => $notes !== '' ? $notes : null,
        ]);
    }

    public function setActive(int $warehouseId, bool $active): void
    {
        if ($warehouseId <= 0) {
            throw new InvalidArgumentException('El P4 seleccionado no es válido.');
        }

        $this->warehouses->setActive($warehouseId, $active);
    }
}
