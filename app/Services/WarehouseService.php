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
        $useSubunit = (string) ($input['use_p4_subunit'] ?? '') === '1';
        $subunitId = (int) ($input['p4_subunit_id'] ?? 0);
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($organizationUnitId <= 0) {
            throw new InvalidArgumentException('Busque y seleccione la Dirección, Zona o Servicio responsable del P4.');
        }

        if ($responsibleEmployeeId <= 0) {
            throw new InvalidArgumentException('Busque y seleccione el funcionario responsable del P4.');
        }

        $unit = $this->rrhh->findUnit($organizationUnitId);
        if (!P4UnitHierarchy::isCurrent($unit)) {
            throw new InvalidArgumentException('La unidad seleccionada no está vigente en RRHH2029.');
        }

        if (!in_array(
            (string) ($unit['unit_type'] ?? ''),
            RrhhRepository::P4_ELIGIBLE_UNIT_TYPES,
            true
        )) {
            throw new InvalidArgumentException(
                'La cabecera debe ser una Dirección, Zona Policial o Servicio oficial de RRHH2029.'
            );
        }

        if (!$useSubunit && $subunitId > 0) {
            throw new InvalidArgumentException('Active la opción de dependencia para vincular un P4 subordinado.');
        }
        if ($useSubunit && $subunitId <= 0) {
            throw new InvalidArgumentException('Seleccione una dependencia o centro oficial debajo de la cabecera.');
        }

        $targetUnit = $unit;
        if ($useSubunit) {
            if (!$this->rrhh->isDescendantOf($subunitId, $organizationUnitId)) {
                throw new InvalidArgumentException(
                    'La dependencia seleccionada no está vigente o no pertenece a esa cabecera en RRHH2029.'
                );
            }
            $targetUnit = $this->rrhh->findUnit($subunitId);
            if (!P4UnitHierarchy::isCurrent($targetUnit)) {
                throw new InvalidArgumentException('La dependencia seleccionada no está vigente.');
            }
        }
        $targetUnitId = (int) $targetUnit['id'];

        if ($this->warehouses->existsForOrganizationUnit($targetUnitId)) {
            throw new InvalidArgumentException(
                'La unidad seleccionada ya tiene un almacén/P4 configurado en SINDESG.'
            );
        }

        $responsible = $this->employees->findEligibleEmployee($responsibleEmployeeId);
        if ($responsible === null) {
            throw new InvalidArgumentException(
                'El responsable seleccionado no está disponible como funcionario vigente en RRHH2029.'
            );
        }

        return $this->warehouses->createP4([
            'organization_unit_id' => $targetUnitId,
            'organization_unit_code_snapshot' => $targetUnit['system_code'] ?: $targetUnit['institutional_code'],
            'organization_unit_name_snapshot' => $targetUnit['official_name'],
            'organization_unit_type_snapshot' => $targetUnit['unit_type'],
            'p4_head_unit_id' => $organizationUnitId,
            'p4_head_unit_code_snapshot' => $unit['system_code'] ?: $unit['institutional_code'],
            'p4_head_unit_name_snapshot' => $unit['official_name'],
            'p4_head_unit_type_snapshot' => $unit['unit_type'],
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
