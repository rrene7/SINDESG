<?php
declare(strict_types=1);

/**
 * Solo una vez en el equipo XAMPP, DESPUÉS del respaldo.
 * Ejecuta únicamente la migración 019 de conciliación nominal.
 * No se conecta con RRHH2029 y no cambia existencias ni solicitudes.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = $database->connection('sindesg');
$name = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($name !== 'sindesg') {
    throw new RuntimeException('La base de destino debe ser sindesg. Detectada: ' . $name);
}
$path = dirname(__DIR__) . '/database/schema/019_p4_nominal_validation.sql';
$contents = file_get_contents($path);
if (!is_string($contents) || $contents === '') {
    throw new RuntimeException('No se encontró la migración 019.');
}
$contents = preg_replace('/^\s*--[^\r\n]*$/m', '', $contents);
$statements = preg_split('/;\s*(?:\r?\n|$)/', (string) $contents) ?: [];
foreach ($statements as $sql) {
    $sql = trim($sql);
    if ($sql !== '') {
        $pdo->exec($sql);
    }
}
$check = $pdo->query("SHOW TABLES LIKE 'p4_nominal_validation_decisions'")->fetchColumn();
if (!$check) {
    throw new RuntimeException('La tabla de validación P4 no fue creada.');
}
$registered = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration = :migration');
$registered->execute(['migration' => '019_p4_nominal_validation']);
if ((int) $registered->fetchColumn() !== 1) {
    throw new RuntimeException('No se registró la migración 019.');
}
echo "[OK] SINDESG: migración 019 instalada. RRHH2029 no modificado.\n";
