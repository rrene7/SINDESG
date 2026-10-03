<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Database;

final class RrhhEmployeeDirectory
{
    public function __construct(private Database $database)
    {
    }

    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $limit = max(1, min($limit, 30));
        $term = '%' . $query . '%';

        $sql = '
            SELECT
                e.id AS employee_id,
                e.document_number,
                e.first_name,
                e.last_name,
                pa.position_number,
                r.name AS rank_name,
                s.name AS status_name,
                ou.id AS organization_unit_id,
                ou.official_name AS organization_unit_name,
                (
                    SELECT ancestor.official_name
                    FROM organization_units_current ancestor
                    WHERE FIND_IN_SET(
                        LPAD(CAST(ancestor.id AS CHAR), 6, "0"),
                        REPLACE(
                            COALESCE(ou.hierarchy_path, LPAD(CAST(ou.id AS CHAR), 6, "0")),
                            "/",
                            ","
                        )
                    ) > 0
                      AND ancestor.unit_type IN ("direccion_nacional", "direccion_general", "direccion")
                    ORDER BY LENGTH(COALESCE(ancestor.hierarchy_path, "")) DESC, ancestor.id DESC
                    LIMIT 1
                ) AS direction_name,
                (
                    SELECT ancestor.official_name
                    FROM organization_units_current ancestor
                    WHERE FIND_IN_SET(
                        LPAD(CAST(ancestor.id AS CHAR), 6, "0"),
                        REPLACE(
                            COALESCE(ou.hierarchy_path, LPAD(CAST(ou.id AS CHAR), 6, "0")),
                            "/",
                            ","
                        )
                    ) > 0
                      AND ancestor.unit_type = "oficina"
                    ORDER BY LENGTH(COALESCE(ancestor.hierarchy_path, "")) DESC, ancestor.id DESC
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
              AND COALESCE(s.blocks_actions, 0) = 0
              AND COALESCE(s.is_active, 1) = 1
              AND (
                    e.document_number LIKE :document_term
                 OR e.first_name LIKE :first_term
                 OR e.last_name LIKE :last_term
                 OR CONCAT_WS(" ", e.first_name, e.last_name) LIKE :full_term
                 OR CAST(pa.position_number AS CHAR) LIKE :position_term
              )
            ORDER BY e.last_name, e.first_name, e.id
            LIMIT ' . $limit;

        $rows = $this->database->rrhhRead($sql, [
            'document_term' => $term,
            'first_term' => $term,
            'last_term' => $term,
            'full_term' => $term,
            'position_term' => $term,
        ]);

        return array_map([$this, 'normalize'], $rows);
    }

    public function findEligibleEmployee(int $employeeId): ?array
    {
        if ($employeeId <= 0) {
            return null;
        }

        $sql = '
            SELECT
                e.id AS employee_id,
                e.document_number,
                e.first_name,
                e.last_name,
                pa.position_number,
                r.name AS rank_name,
                s.name AS status_name,
                ou.id AS organization_unit_id,
                ou.official_name AS organization_unit_name,
                (
                    SELECT ancestor.official_name
                    FROM organization_units_current ancestor
                    WHERE FIND_IN_SET(
                        LPAD(CAST(ancestor.id AS CHAR), 6, "0"),
                        REPLACE(
                            COALESCE(ou.hierarchy_path, LPAD(CAST(ou.id AS CHAR), 6, "0")),
                            "/",
                            ","
                        )
                    ) > 0
                      AND ancestor.unit_type IN ("direccion_nacional", "direccion_general", "direccion")
                    ORDER BY LENGTH(COALESCE(ancestor.hierarchy_path, "")) DESC, ancestor.id DESC
                    LIMIT 1
                ) AS direction_name,
                (
                    SELECT ancestor.official_name
                    FROM organization_units_current ancestor
                    WHERE FIND_IN_SET(
                        LPAD(CAST(ancestor.id AS CHAR), 6, "0"),
                        REPLACE(
                            COALESCE(ou.hierarchy_path, LPAD(CAST(ou.id AS CHAR), 6, "0")),
                            "/",
                            ","
                        )
                    ) > 0
                      AND ancestor.unit_type = "oficina"
                    ORDER BY LENGTH(COALESCE(ancestor.hierarchy_path, "")) DESC, ancestor.id DESC
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
            WHERE e.id = :employee_id
              AND e.document_number IS NOT NULL
              AND TRIM(e.document_number) <> ""
              AND COALESCE(s.blocks_actions, 0) = 0
              AND COALESCE(s.is_active, 1) = 1
            ORDER BY pa.position_number DESC
            LIMIT 1
        ';

        $rows = $this->database->rrhhRead($sql, ['employee_id' => $employeeId]);

        return isset($rows[0]) ? $this->normalize($rows[0]) : null;
    }

    private function normalize(array $row): array
    {
        $rank = trim((string) ($row['rank_name'] ?? ''));
        $name = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));

        return [
            'employee_id' => (int) ($row['employee_id'] ?? 0),
            'document_number' => (string) ($row['document_number'] ?? ''),
            'first_name' => (string) ($row['first_name'] ?? ''),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'position_number' => $row['position_number'] !== null ? (string) $row['position_number'] : null,
            'rank_name' => $rank !== '' ? $rank : null,
            'status_name' => (string) ($row['status_name'] ?? ''),
            'organization_unit_id' => $row['organization_unit_id'] !== null
                ? (int) $row['organization_unit_id']
                : null,
            'organization_unit_name' => (string) ($row['organization_unit_name'] ?? ''),
            'direction_name' => (string) ($row['direction_name'] ?? ''),
            'office_name' => (string) ($row['office_name'] ?? ''),
            'display_name' => trim(($rank !== '' ? $rank . ' · ' : '') . $name),
        ];
    }
}
