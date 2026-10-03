<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class RrhhRepository
{
    public function __construct(private Database $database)
    {
    }

    public function stats(): array
    {
        $rows = $this->database->rrhhRead(<<<'SQL'
            SELECT
                (
                    SELECT COUNT(DISTINCT e.id)
                    FROM employees e
                    INNER JOIN employee_position_assignments pa
                        ON pa.employee_id = e.id
                       AND pa.end_date IS NULL
                    INNER JOIN employee_unit_assignments ua
                        ON ua.employee_id = e.id
                       AND ua.is_primary = 1
                       AND ua.status = 'active'
                       AND ua.end_date IS NULL
                    WHERE e.document_number IS NOT NULL
                      AND TRIM(e.document_number) <> ''
                ) AS current_people,
                (
                    SELECT COUNT(*)
                    FROM organization_units_current ou
                    WHERE ou.is_active = 1
                      AND COALESCE(ou.lifecycle_status, 'vigente') = 'vigente'
                ) AS active_units,
                (
                    SELECT COUNT(*)
                    FROM ranks
                ) AS ranks_count
        SQL);

        $row = $rows[0] ?? [];

        return [
            'current_people' => (int) ($row['current_people'] ?? 0),
            'active_units' => (int) ($row['active_units'] ?? 0),
            'ranks_count' => (int) ($row['ranks_count'] ?? 0),
        ];
    }

    public function searchPeople(string $query, int $limit = 50): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min($limit, 100));
        $term = '%' . $query . '%';

        return $this->database->rrhhRead('
            SELECT
                e.id AS employee_id,
                e.document_number,
                e.first_name,
                e.last_name,
                pa.position_number,
                r.name AS rank_name,
                s.name AS status_name,
                ou.id AS organization_unit_id,
                ou.system_code AS organization_unit_code,
                ou.official_name AS organization_unit_name,
                ou.unit_type,
                ou.hierarchy_path,
                (
                    SELECT ancestor.official_name
                    FROM organization_units_current ancestor
                    WHERE FIND_IN_SET(
                        LPAD(CAST(ancestor.id AS CHAR), 6, '0'),
                        REPLACE(
                            COALESCE(ou.hierarchy_path, LPAD(CAST(ou.id AS CHAR), 6, '0')),
                            '/',
                            ','
                        )
                    ) > 0
                      AND ancestor.unit_type IN ('direccion_nacional', 'direccion_general', 'direccion')
                    ORDER BY LENGTH(COALESCE(ancestor.hierarchy_path, '')) DESC, ancestor.id DESC
                    LIMIT 1
                ) AS direction_name,
                (
                    SELECT ancestor.official_name
                    FROM organization_units_current ancestor
                    WHERE FIND_IN_SET(
                        LPAD(CAST(ancestor.id AS CHAR), 6, '0'),
                        REPLACE(
                            COALESCE(ou.hierarchy_path, LPAD(CAST(ou.id AS CHAR), 6, '0')),
                            '/',
                            ','
                        )
                    ) > 0
                      AND ancestor.unit_type = 'oficina'
                    ORDER BY LENGTH(COALESCE(ancestor.hierarchy_path, '')) DESC, ancestor.id DESC
                    LIMIT 1
                ) AS office_name
            FROM employees e
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN statuses s ON s.id = e.status_id
            LEFT JOIN employee_position_assignments pa
                ON pa.employee_id = e.id
               AND pa.end_date IS NULL
            LEFT JOIN employee_unit_assignments ua
                ON ua.employee_id = e.id
               AND ua.is_primary = 1
               AND ua.status = "active"
               AND ua.end_date IS NULL
            LEFT JOIN organization_units_current ou
                ON ou.id = ua.organization_unit_id
            WHERE e.document_number IS NOT NULL
              AND TRIM(e.document_number) <> ""
              AND (
                    e.document_number LIKE :document_term
                 OR e.first_name LIKE :first_term
                 OR e.last_name LIKE :last_term
                 OR CONCAT_WS(" ", e.first_name, e.last_name) LIKE :full_term
                 OR CAST(pa.position_number AS CHAR) LIKE :position_term
              )
            ORDER BY e.last_name, e.first_name, e.id
            LIMIT ' . $limit, [
                'document_term' => $term,
                'first_term' => $term,
                'last_term' => $term,
                'full_term' => $term,
                'position_term' => $term,
            ]);
    }

    public function findPerson(int $employeeId): ?array
    {
        if ($employeeId <= 0) {
            return null;
        }

        $rows = $this->database->rrhhRead(<<<'SQL'
            SELECT
                e.id AS employee_id,
                e.document_number,
                e.first_name,
                e.last_name,
                e.sex,
                e.hire_date,
                pa.position_number,
                pa.start_date AS position_start_date,
                r.id AS rank_id,
                r.name AS rank_name,
                r.abbreviation AS rank_abbreviation,
                s.id AS status_id,
                s.name AS status_name,
                ou.id AS organization_unit_id,
                ou.system_code AS organization_unit_code,
                ou.institutional_code AS organization_unit_institutional_code,
                ou.official_name AS organization_unit_name,
                ou.official_abbreviation AS organization_unit_abbreviation,
                ou.unit_type,
                ou.moi_level,
                ou.hierarchy_path,
                (
                    SELECT ancestor.official_name
                    FROM organization_units_current ancestor
                    WHERE FIND_IN_SET(
                        LPAD(CAST(ancestor.id AS CHAR), 6, '0'),
                        REPLACE(
                            COALESCE(ou.hierarchy_path, LPAD(CAST(ou.id AS CHAR), 6, '0')),
                            '/',
                            ','
                        )
                    ) > 0
                      AND ancestor.unit_type IN ('direccion_nacional', 'direccion_general', 'direccion')
                    ORDER BY LENGTH(COALESCE(ancestor.hierarchy_path, '')) DESC, ancestor.id DESC
                    LIMIT 1
                ) AS direction_name,
                (
                    SELECT ancestor.official_name
                    FROM organization_units_current ancestor
                    WHERE FIND_IN_SET(
                        LPAD(CAST(ancestor.id AS CHAR), 6, '0'),
                        REPLACE(
                            COALESCE(ou.hierarchy_path, LPAD(CAST(ou.id AS CHAR), 6, '0')),
                            '/',
                            ','
                        )
                    ) > 0
                      AND ancestor.unit_type = 'oficina'
                    ORDER BY LENGTH(COALESCE(ancestor.hierarchy_path, '')) DESC, ancestor.id DESC
                    LIMIT 1
                ) AS office_name,
                ua.start_date AS unit_assignment_start_date
            FROM employees e
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN statuses s ON s.id = e.status_id
            LEFT JOIN employee_position_assignments pa
                ON pa.employee_id = e.id
               AND pa.end_date IS NULL
            LEFT JOIN employee_unit_assignments ua
                ON ua.employee_id = e.id
               AND ua.is_primary = 1
               AND ua.status = 'active'
               AND ua.end_date IS NULL
            LEFT JOIN organization_units_current ou
                ON ou.id = ua.organization_unit_id
            WHERE e.id = :employee_id
            ORDER BY pa.position_number DESC
            LIMIT 1
        SQL, ['employee_id' => $employeeId]);

        return $rows[0] ?? null;
    }

    public function rootUnits(): array
    {
        return $this->database->rrhhRead(<<<'SQL'
            SELECT
                ou.id,
                ou.parent_id,
                ou.system_code,
                ou.institutional_code,
                ou.official_name,
                ou.official_abbreviation,
                ou.unit_type,
                ou.moi_level,
                ou.hierarchy_path,
                ou.display_order,
                (
                    SELECT COUNT(*)
                    FROM organization_units_current child
                    WHERE child.parent_id = ou.id
                      AND child.is_active = 1
                      AND COALESCE(child.lifecycle_status, 'vigente') = 'vigente'
                ) AS child_count,
                (
                    SELECT COUNT(*)
                    FROM employee_unit_assignments ua
                    WHERE ua.organization_unit_id = ou.id
                      AND ua.is_primary = 1
                      AND ua.status = 'active'
                      AND ua.end_date IS NULL
                ) AS personnel_count
            FROM organization_units_current ou
            WHERE ou.parent_id IS NULL
              AND ou.is_active = 1
              AND COALESCE(ou.lifecycle_status, 'vigente') = 'vigente'
            ORDER BY ou.display_order, ou.official_name
        SQL);
    }

    public function findUnit(int $unitId): ?array
    {
        if ($unitId <= 0) {
            return null;
        }

        $rows = $this->database->rrhhRead(<<<'SQL'
            SELECT
                ou.id,
                ou.parent_id,
                parent.official_name AS parent_name,
                ou.system_code,
                ou.institutional_code,
                ou.official_name,
                ou.official_abbreviation,
                ou.unit_type,
                ou.moi_level,
                ou.hierarchy_path,
                ou.lifecycle_status,
                ou.is_active
            FROM organization_units_current ou
            LEFT JOIN organization_units_current parent ON parent.id = ou.parent_id
            WHERE ou.id = :unit_id
            LIMIT 1
        SQL, ['unit_id' => $unitId]);

        return $rows[0] ?? null;
    }

    public function childUnits(int $unitId): array
    {
        return $this->database->rrhhRead(<<<'SQL'
            SELECT
                ou.id,
                ou.parent_id,
                ou.system_code,
                ou.official_name,
                ou.official_abbreviation,
                ou.unit_type,
                ou.moi_level,
                ou.hierarchy_path,
                (
                    SELECT COUNT(*)
                    FROM organization_units_current child
                    WHERE child.parent_id = ou.id
                      AND child.is_active = 1
                      AND COALESCE(child.lifecycle_status, 'vigente') = 'vigente'
                ) AS child_count,
                (
                    SELECT COUNT(*)
                    FROM employee_unit_assignments ua
                    WHERE ua.organization_unit_id = ou.id
                      AND ua.is_primary = 1
                      AND ua.status = 'active'
                      AND ua.end_date IS NULL
                ) AS personnel_count
            FROM organization_units_current ou
            WHERE ou.parent_id = :unit_id
              AND ou.is_active = 1
              AND COALESCE(ou.lifecycle_status, 'vigente') = 'vigente'
            ORDER BY ou.display_order, ou.official_name
        SQL, ['unit_id' => $unitId]);
    }

    public function peopleByUnit(int $unitId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 200));

        return $this->database->rrhhRead('
            SELECT
                e.id AS employee_id,
                e.document_number,
                e.first_name,
                e.last_name,
                pa.position_number,
                r.name AS rank_name,
                s.name AS status_name
            FROM employee_unit_assignments ua
            INNER JOIN employees e ON e.id = ua.employee_id
            LEFT JOIN ranks r ON r.id = e.rank_id
            LEFT JOIN statuses s ON s.id = e.status_id
            LEFT JOIN employee_position_assignments pa
                ON pa.employee_id = e.id
               AND pa.end_date IS NULL
            WHERE ua.organization_unit_id = :unit_id
              AND ua.is_primary = 1
              AND ua.status = "active"
              AND ua.end_date IS NULL
            ORDER BY r.sort_order, e.last_name, e.first_name
            LIMIT ' . $limit, ['unit_id' => $unitId]);
    }
}
