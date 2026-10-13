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
                ou.unit_type,
                ou.hierarchy_path
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

        $rows = $this->withOrganizationContext($rows);

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
                ou.unit_type,
                ou.hierarchy_path
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

        $rows = $this->withOrganizationContext($rows);

        return isset($rows[0]) ? $this->normalize($rows[0]) : null;
    }

    /**
     * Resuelve funcionarios activos por ID o posición en lotes, siempre solo SELECT.
     * La posición puede no ser única: los llamadores deben rechazar ambigüedades.
     *
     * @param list<int|string> $values
     * @return list<array<string,mixed>>
     */
    public function resolveBulk(string $field, array $values): array
    {
        if (!in_array($field, ['employee_id', 'position_number'], true)) {
            throw new \InvalidArgumentException('Campo RRHH no permitido.');
        }
        $numbers = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if (!ctype_digit($value) || (int) $value <= 0) {
                throw new \InvalidArgumentException('Solo se admiten IDs o posiciones numéricas.');
            }
            $numbers[(int) $value] = (int) $value;
        }
        if (count($numbers) > 1000) {
            throw new \InvalidArgumentException('El máximo es 1,000 funcionarios por operación.');
        }
        if ($numbers === []) {
            return [];
        }

        $result = [];
        foreach (array_chunk(array_values($numbers), 100) as $chunk) {
            $ids = array_fill_keys($chunk, true);
            $params = [];
            $main = [];
            $assignment = [];
            $historical = [];
            foreach ($chunk as $i => $id) {
                $params['target' . $i] = $id;
                $main[] = ':target' . $i;
                if ($field === 'position_number') {
                    $params['assignment' . $i] = $id;
                    $params['historical' . $i] = $id;
                    $assignment[] = ':assignment' . $i;
                    $historical[] = ':historical' . $i;
                }
            }

            // RRHH2029's own EmployeeController searches positions using
            // employees.legacy_position (current) and
            // employees.last_position_number (previous). The older lookup
            // only checked open employee_position_assignments; it falsely
            // treated many real RRHH dossiers as nonexistent.
            $criteria = $field === 'employee_id'
                ? 'e.id IN (' . implode(', ', $main) . ')'
                : '(e.legacy_position IN (' . implode(', ', $main) . ')'
                  . ' OR pa.position_number IN (' . implode(', ', $assignment) . ')'
                  . ' OR e.last_position_number IN (' . implode(', ', $historical) . '))';

            $sql = '
                SELECT e.id AS employee_id,
                       e.document_number,
                       COALESCE(
                           NULLIF(TRIM(CONCAT_WS(" ",
                               NULLIF(e.first_name_1, ""), NULLIF(e.first_name_2, ""))), ""),
                           e.first_name
                       ) AS first_name,
                       COALESCE(
                           NULLIF(TRIM(CONCAT_WS(" ",
                               NULLIF(e.last_name_1, ""), NULLIF(e.last_name_2, ""))), ""),
                           e.last_name
                       ) AS last_name,
                       e.legacy_position,
                       e.last_position_number,
                       pa.position_number AS assignment_position,
                       r.name AS rank_name,
                       s.name AS status_name,
                       COALESCE(s.blocks_actions, 0) AS blocks_actions,
                       COALESCE(s.is_active, 1) AS status_is_active,
                       ou.id AS organization_unit_id,
                       ou.official_name AS organization_unit_name,
                       ou.unit_type, ou.hierarchy_path
                FROM employees e
                LEFT JOIN ranks r ON r.id = e.rank_id
                LEFT JOIN statuses s ON s.id = e.status_id
                LEFT JOIN employee_position_assignments pa
                    ON pa.employee_id = e.id AND pa.end_date IS NULL
                LEFT JOIN employee_unit_assignments ua
                    ON ua.employee_id = e.id AND ua.is_primary = 1
                   AND ua.status = "active" AND ua.end_date IS NULL
                LEFT JOIN organization_units_current ou
                    ON ou.id = ua.organization_unit_id
                WHERE ' . $criteria . '
                ORDER BY e.id';

            $rows = $this->withOrganizationContext($this->database->rrhhRead($sql, $params));
            foreach ($rows as $row) {
                $current = (int) ($row['legacy_position'] ?? 0);
                $assigned = (int) ($row['assignment_position'] ?? 0);
                $previous = (int) ($row['last_position_number'] ?? 0);
                $eligible = trim((string) ($row['document_number'] ?? '')) !== ''
                    && (int) ($row['blocks_actions'] ?? 0) === 0
                    && (int) ($row['status_is_active'] ?? 1) === 1;

                if ($field === 'employee_id') {
                    $position = $current ?: $assigned;
                    $row['position_number'] = $position ?: null;
                    $row['position_source'] = $current ? 'current' : ($assigned ? 'assignment' : 'missing');
                    $row['is_rrhh_eligible'] = $eligible;
                    $result[] = $this->normalize($row);
                    continue;
                }

                $sources = [];
                if ($current > 0 && isset($ids[$current])) {
                    $sources[$current] = 'current';
                }
                if ($assigned > 0 && isset($ids[$assigned]) && !isset($sources[$assigned])) {
                    // RRHH's current dossier position has priority. An open
                    // assignment pointing somewhere else is not auto-verified.
                    $sources[$assigned] = $current > 0 ? 'assignment_conflict' : 'assignment';
                }
                if ($previous > 0 && isset($ids[$previous]) && !isset($sources[$previous])) {
                    $sources[$previous] = 'historical';
                }
                foreach ($sources as $position => $source) {
                    $match = $row;
                    $match['position_number'] = (string) $position;
                    $match['position_source'] = $source;
                    $match['is_rrhh_eligible'] = $eligible;
                    $result[] = $this->normalize($match);
                }
            }
        }
        return $result;
    }

    public function belongsToOrganizationUnit(int $employeeId, int $organizationUnitId): bool
    {
        if ($employeeId <= 0 || $organizationUnitId <= 0) {
            return false;
        }

        $rows = $this->database->rrhhRead(
            'SELECT
                ou.id AS organization_unit_id,
                ou.hierarchy_path
             FROM employees e
             LEFT JOIN statuses s ON s.id = e.status_id
             LEFT JOIN employee_unit_assignments ua
                ON ua.employee_id = e.id
               AND ua.is_primary = 1
               AND ua.status = "active"
               AND ua.end_date IS NULL
             LEFT JOIN organization_units_current ou
                ON ou.id = ua.organization_unit_id
             WHERE e.id = :employee_id
               AND COALESCE(s.blocks_actions, 0) = 0
               AND COALESCE(s.is_active, 1) = 1
             LIMIT 1',
            ['employee_id' => $employeeId]
        );

        if (!isset($rows[0])) {
            return false;
        }

        return in_array(
            $organizationUnitId,
            $this->hierarchyIds($rows[0]),
            true
        );
    }

    private function withOrganizationContext(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            foreach ($this->hierarchyIds($row) as $unitId) {
                $ids[$unitId] = true;
            }
        }

        $unitsById = [];
        if ($ids !== []) {
            $params = [];
            $placeholders = [];

            foreach (array_keys($ids) as $index => $unitId) {
                $key = 'unit_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $unitId;
            }

            $units = $this->database->rrhhRead(
                'SELECT id, official_name, unit_type
                 FROM organization_units_current
                 WHERE id IN (' . implode(',', $placeholders) . ')',
                $params
            );

            foreach ($units as $unit) {
                $unitsById[(int) $unit['id']] = $unit;
            }
        }

        foreach ($rows as &$row) {
            $directionName = '';
            $officeName = '';

            foreach ($this->hierarchyIds($row) as $unitId) {
                $unit = $unitsById[$unitId] ?? null;
                if ($unit === null) {
                    continue;
                }

                $type = (string) ($unit['unit_type'] ?? '');
                $name = (string) ($unit['official_name'] ?? '');

                if (in_array($type, ['direccion_nacional', 'direccion_general', 'direccion'], true)) {
                    $directionName = $name;
                }

                if ($type === 'oficina') {
                    $officeName = $name;
                }
            }

            $row['direction_name'] = $directionName;
            $row['office_name'] = $officeName;
        }
        unset($row);

        return $rows;
    }

    private function hierarchyIds(array $row): array
    {
        $path = trim((string) ($row['hierarchy_path'] ?? ''));
        $ids = [];

        if ($path !== '') {
            foreach (explode('/', $path) as $part) {
                $unitId = (int) ltrim(trim($part), '0');
                if ($unitId > 0) {
                    $ids[] = $unitId;
                }
            }
        }

        $currentUnitId = (int) ($row['organization_unit_id'] ?? 0);
        if ($currentUnitId > 0 && !in_array($currentUnitId, $ids, true)) {
            $ids[] = $currentUnitId;
        }

        return array_values(array_unique($ids));
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
            'position_source' => (string) ($row['position_source'] ?? 'current'),
            'is_rrhh_eligible' => (bool) ($row['is_rrhh_eligible'] ?? true),
            'rank_name' => $rank !== '' ? $rank : null,
            'status_name' => (string) ($row['status_name'] ?? ''),
            'organization_unit_id' => $row['organization_unit_id'] !== null
                ? (int) $row['organization_unit_id']
                : null,
            'organization_unit_name' => (string) ($row['organization_unit_name'] ?? ''),
            'direction_name' => (string) ($row['direction_name'] ?? ''),
            'office_name' => (string) ($row['office_name'] ?? ''),
            'display_name' => trim(($rank !== '' ? $rank . ' · ' : '') . $name),
            'unit_path_ids' => $this->hierarchyIds($row),
        ];
    }
}
