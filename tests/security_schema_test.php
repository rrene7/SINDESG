<?php
declare(strict_types=1);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema/015_security_audit.sql');

if ($schema === false) {
    fwrite(STDERR, "[ERROR] No se pudo leer 015_security_audit.sql" . PHP_EOL);
    exit(1);
}

foreach ([
    'CREATE TABLE IF NOT EXISTS security_users',
    'CREATE TABLE IF NOT EXISTS security_roles',
    'CREATE TABLE IF NOT EXISTS security_permissions',
    'CREATE TABLE IF NOT EXISTS security_user_roles',
    'CREATE TABLE IF NOT EXISTS security_role_permissions',
    'CREATE TABLE IF NOT EXISTS audit_logs',
    "'ADMIN'",
    "'CENTRAL'",
    "'P4'",
    "'AUDITOR'",
    "'VIEWER'",
    "'admin.manage'",
    "'audit.view'",
    "'security.session_idle_minutes'",
    "VALUES ('015_security_audit')",
] as $fragment) {
    if (!str_contains($schema, $fragment)) {
        fwrite(STDERR, "[ERROR] Esquema de seguridad incompleto: {$fragment}" . PHP_EOL);
        exit(1);
    }
}

if (str_contains(strtolower($schema), 'default_password')) {
    fwrite(STDERR, "[ERROR] No se permiten contraseñas predeterminadas." . PHP_EOL);
    exit(1);
}

echo "[OK] Esquema de seguridad y auditoría validado." . PHP_EOL;
