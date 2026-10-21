<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Read-only advisory. Same normalized name does NOT prove a duplicate.
 * Never merges, renumbers, archives, deletes or rewrites inventory/history.
 */
final class CatalogDuplicateInspector
{
    public static function nameKey(string $name): string
    {
        $name = strtr($name, [
            'Á'=>'A','À'=>'A','Â'=>'A','Ä'=>'A','á'=>'a','à'=>'a','â'=>'a','ä'=>'a',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'Ó'=>'O','Ò'=>'O','Ô'=>'O','Ö'=>'O','ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'Ñ'=>'N','ñ'=>'n',
        ]);
        $name = preg_replace('/[\x{0300}-\x{036F}]/u', '', $name) ?? $name;
        $name = mb_strtolower($name, 'UTF-8');
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? '');
    }

    /**
     * @param array<array<string,mixed>> $products
     * @return array<int,array{key:string,name:string,items:array,category_count:int}>
     */
    public static function groups(array $products): array
    {
        $byName = [];
        foreach ($products as $p) {
            if ((string)($p['status'] ?? '') === 'deleted') continue;
            $key = self::nameKey((string)($p['name'] ?? ''));
            if ($key === '') continue;
            $byName[$key][] = $p;
        }
        $groups = [];
        foreach ($byName as $key => $items) {
            if (count($items) < 2) continue;
            usort($items, static fn(array $a,array $b):int => ((int)$a['id']) <=> ((int)$b['id']));
            $categories = [];
            foreach ($items as $row) $categories[(int)$row['category_id']] = true;
            $groups[] = [
                'key' => $key,
                'name' => (string)$items[0]['name'],
                'items' => $items,
                'category_count' => count($categories),
            ];
        }
        usort($groups, static fn(array $a,array $b):int => strcmp($a['key'],$b['key']));
        return $groups;
    }
}
