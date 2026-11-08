<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\RrhhEmployeeDirectory;

// Numeric positions and institutional documents must avoid unindexed LIKE.
$classification = (new ReflectionClass(RrhhEmployeeDirectory::class))
    ->getMethod('isExactSearchIdentifier');
foreach ([
    ['16972', true],
    ['00016972', true],
    ['8-123-456', true],
    ['123456789', true],
    ['JUAN', false],
    ['MARIA PEREZ', false],
    ['ANA-ROSA', false],
    ['17/2026', false],
    ['', false],
] as [$query, $expected]) {
    if ($classification->invoke(null, $query) !== $expected) {
        throw new RuntimeException('Search mode incorrect for query: ' . $query);
    }
}

$root = dirname(__DIR__);
$directory = (string) file_get_contents($root . '/app/Services/RrhhEmployeeDirectory.php');
$api = (string) file_get_contents($root . '/public/api/employees.php');
$distribution = (string) file_get_contents($root . '/public/assets/js/distribution.js');

foreach ([
    'e.document_number = :document_number',
    'e.legacy_position = :legacy_position',
    'pa.position_number = :assignment_position',
    'candidate.employee_id',
    'e.id IN (',
    'isExactSearchIdentifier($query)',
    'COALESCE(NULLIF(e.legacy_position, 0), pa.position_number)',
    'COALESCE(e.is_position_placeholder, 0) = 0',
    'CONCAT_WS(" ", e.first_name, e.last_name) LIKE :full_term',
    '$this->withOrganizationContext($rows)',
] as $part) {
    if (!str_contains($directory, $part)) {
        throw new RuntimeException('Missing indexed RRHH search guard: ' . $part);
    }
}
if (!str_contains($api, 'sindesg_api_authorized(true)')
    || !str_contains($api, "sindesg_can('requests.view')")
    || !str_contains($api, 'session_write_close()')
    || strpos($api, 'session_write_close()') < strpos($api, "sindesg_can('requests.view')")) {
    throw new RuntimeException('API must authenticate/authorize before unlocking session.');
}

foreach (['new AbortController()', 'controller.abort()', 'controller.signal',
    'sequence += 1', 'window.clearTimeout(timeout)', '8500',
    'La búsqueda tardó demasiado'] as $part) {
    if (!str_contains($distribution, $part)) {
        throw new RuntimeException('Picker lacks cancellation or timeout: ' . $part);
    }
}
echo "[OK] Búsqueda exacta de posición/cédula y selector RRHH de solo lectura.\n";
