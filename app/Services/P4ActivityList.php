<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Small read-only homepage digest. Records remain in existing source tables
 * and SQL-scoped modules; the dashboard never renders an unbounded list.
 */
final class P4ActivityList
{
    public const VISIBLE = 3;
    public const EXPANDABLE = 5;

    /**
     * The caller supplies newest-first records from the authorized P4 scope.
     * @param list<array<string,mixed>> $recent
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>}
     */
    public static function split(array $recent): array
    {
        return [
            array_slice($recent, 0, self::VISIBLE),
            array_slice($recent, self::VISIBLE, self::EXPANDABLE),
        ];
    }
}
