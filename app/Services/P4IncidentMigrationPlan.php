<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Fail-closed migration 023 SQL parser.
 * Git on Windows may checkout the SQL as CRLF; comments must still be stripped.
 * The result deliberately permits only the two table definitions and
 * the schema_migrations marker, and never modifies existing inventory rows.
 */
final class P4IncidentMigrationPlan
{
    /** @return list<string> Executable SQL after a validated USE sindesg. */
    public static function statements(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        // Do NOT use \s here: Windows CRLF + $ prevented comment matching.
        $withoutComments = preg_replace('/^[ \t]*--[^\n]*$/m', '', $sql);
        if (!is_string($withoutComments)) {
            throw new RuntimeException('No se pudieron normalizar los comentarios del SQL.');
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(';', $withoutComments)),
            static fn(string $part): bool => $part !== ''
        ));

        $matches = count($parts) === 4
            && $parts[0] === 'USE sindesg'
            && preg_match('/^CREATE TABLE IF NOT EXISTS p4_incident_cases\s*\(.*\)\s*ENGINE=InnoDB\s+DEFAULT CHARSET=utf8mb4\s+COLLATE=utf8mb4_unicode_ci$/is', $parts[1]) === 1
            && preg_match('/^CREATE TABLE IF NOT EXISTS p4_incident_events\s*\(.*\)\s*ENGINE=InnoDB\s+DEFAULT CHARSET=utf8mb4\s+COLLATE=utf8mb4_unicode_ci$/is', $parts[2]) === 1
            && preg_match("/^INSERT INTO schema_migrations\s*\(migration\)\s*VALUES\s*\('023_p4_incident_intake'\)\s*ON DUPLICATE KEY UPDATE migration\s*=\s*VALUES\s*\(migration\)$/is", $parts[3]) === 1
            && str_contains($parts[1], 'fk_p4_incident_delivery_item')
            && str_contains($parts[1], 'fk_p4_incident_adjustment')
            && str_contains($parts[1], 'uq_p4_incident_single_adjustment')
            && str_contains($parts[2], 'fk_p4_incident_event_case');
        if (!$matches) {
            throw new RuntimeException(
                'La migración 023 no coincide con las cuatro instrucciones autorizadas. ' .
                'Detectadas: ' . count($parts) . '. Sin cambios.'
            );
        }
        return array_slice($parts, 1);
    }
}
