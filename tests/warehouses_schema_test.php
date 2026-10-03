<?php
declare(strict_types=1);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema/008_warehouses_p4.sql');

if ($schema === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer 008_warehouses_p4.sql" . PHP_EOL);
    exit(1);
}

foreach ([
    'organization_unit_code_snapshot',
    'organization_unit_name_snapshot',
    'responsible_employee_id',
    'responsible_label_snapshot',
    'uq_warehouses_org_unit_unique',
    'status_changed_at',
    "VALUES ('008_warehouses_p4')",
] as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Falta regla de almacenes/P4: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (stripos($schema, 'USE rrhh2029') !== false) {
    fwrite(STDERR, "[ERROR] La migración P4 no puede modificar RRHH2029." . PHP_EOL);
    exit(1);
}

echo "[OK] Esquema de Almacenes y P4 validado." . PHP_EOL;
