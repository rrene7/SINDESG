<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Services/P4IncidentMigrationPlan.php';

use App\Services\P4IncidentMigrationPlan as Plan;

$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$source = (string) file_get_contents(dirname(__DIR__) . '/database/schema/023_p4_incident_intake.sql');
$lf = str_replace(["\r\n", "\r"], "\n", $source);
$variants = [
    'LF' => $lf,
    'Windows CRLF' => str_replace("\n", "\r\n", $lf),
    'Old Mac CR' => str_replace("\n", "\r", $lf),
    'UTF-8 BOM + Windows CRLF' => "\xEF\xBB\xBF" . str_replace("\n", "\r\n", $lf),
];
$expected = Plan::statements($lf);
$assert(count($expected) === 3, 'El plan solo admite dos tablas y el marcador.');
foreach ($variants as $name => $contents) {
    $parsed = Plan::statements($contents);
    $assert($parsed === $expected, $name . ': los comentarios o los saltos de línea cambiaron el SQL.');
}
$reject = static function(string $sql, string $message): void {
    try {
        Plan::statements($sql);
    } catch (RuntimeException $exception) {
        return;
    }
    throw new RuntimeException('Se aceptó SQL no autorizado: ' . $message);
};
$reject(str_replace('USE sindesg;', 'USE rrhh2029;', $lf), 'Otra base');
$reject($lf . "\nDROP TABLE individual_deliveries;\n", 'Instrucción adicional');
$reject(str_replace('uq_p4_incident_single_adjustment', 'other_index', $lf), 'Sin índice único');
$reject(str_replace('023_p4_incident_intake', '023_unapproved', $lf), 'Marcador ajeno');

$installer = (string) file_get_contents(dirname(__DIR__) . '/scripts/install_p4_incidents.php');
foreach ([
    'BackupSupport::databaseConfig(', 'BackupSupport::manifest(',
    "'SELECT DATABASE()'", 'P4IncidentMigrationPlan::statements(',
    "BackupSupport::option(\$argv, 'confirm') !== 'APLICAR-023'",
    "BackupSupport::option(\$argv, 'backup')",
    "if (\$cases !== 0 || \$events !== 0 || \$recorded !== 0)",
] as $fragment) {
    $assert(str_contains($installer, $fragment), 'Control ausente en instalador: ' . $fragment);
}
$assert(!str_contains($installer, "connection('rrhh')")
    && !str_contains($installer, 'DELETE FROM')
    && !str_contains($installer, 'UPDATE inventory_movements'),
    'Un instalador de dos tablas no puede intervenir RRHH o inventario.');
echo "[OK] Migración 023: parser LF/CRLF/BOM, solo SINDESG, respaldo, bloqueo parcial e instalador CLI.\n";
