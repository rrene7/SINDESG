<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Presentation-only separation of the P4 <-> Central relationship from
 * employee handovers. Input must already be SQL-scoped to the user's P4.
 * Never invent a request -> transfer association: these are separate documents.
 */
final class P4DashboardActivitySections
{
    /**
     * @param list<array<string,mixed>> $records
     * @return array{requests:list<array<string,mixed>>,transfers:list<array<string,mixed>>,deliveries:list<array<string,mixed>>}
     */
    public static function group(array $records): array
    {
        $groups = ['requests'=>[], 'transfers'=>[], 'deliveries'=>[]];
        foreach ($records as $record) {
            $kind = (string) ($record['type'] ?? '');
            if (isset($groups[$kind])) {
                $groups[$kind][] = $record;
            }
        }
        foreach ($groups as &$rows) {
            usort($rows, static function(array $left,array $right): int {
                $byTime = strcmp((string)($right['event_at'] ?? ''), (string)($left['event_at'] ?? ''));
                return $byTime !== 0 ? $byTime : ((int)($right['id'] ?? 0) <=> (int)($left['id'] ?? 0));
            });
        }
        unset($rows);
        return $groups;
    }
}
