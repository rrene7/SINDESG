<?php
declare(strict_types=1);

/**
 * Static regression contract; does not require an Apache server or databases.
 * The real TLS and HTTP 403/404 acceptance test remains mandatory on deployment.
 */
$root = dirname(__DIR__);
$files = [
    'rules' => '.htaccess',
    'deploy' => 'deploy-windows.ps1',
    'ready' => 'scripts/readiness.php',
    'test' => 'scripts/verify-http-protection.ps1',
    'manual' => 'docs/PREPARACION_SERVIDOR_P4.md',
];
$data = [];
foreach ($files as $key => $path) {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content) || $content === '') {
        throw new RuntimeException('Falta el artefacto de publicación: ' . $path);
    }
    $data[$key] = $content;
}

$apache = $data['rules'];
foreach ([
    'Options -Indexes',
    'RewriteEngine On',
    'app|config|database|docs|scripts|tests',
    'storage/(?:backups|cache|logs|deployments)',
    'storage/uploads/',
    'php[0-9]?',
    'RewriteRule (^|/)\\.',
    '[F,L',
] as $fragment) {
    if (!str_contains($apache, $fragment)) {
        throw new RuntimeException('Regla de protección Apache faltante: ' . $fragment);
    }
}
if (!str_contains($data['deploy'], '"README.md", ".gitignore", ".htaccess"')
    || !str_contains($data['deploy'], 'if ($Production -and $SkipBackup)')
    || !str_contains($data['deploy'], 'Out-String).Trim()')) {
    throw new RuntimeException('El desplegador puede omitir protección, respaldo o fallar en HEAD separado.');
}

if (!str_contains($data['ready'], "\$environment === 'production'")
    || !str_contains($data['ready'], '$apacheRulesPath')
    || !str_contains($data['ready'], "strtolower(\$username) === 'root'")
    || !str_contains($data['ready'], "\$databaseName !== \$expectedName")
    || !str_contains($data['ready'], "['rrhh']['read_only']")) {
    throw new RuntimeException('El preflight de producción permite publicación insegura.');
}
if (!str_contains($data['test'], "Scheme -cne 'https'")
    || !str_contains($data['test'], 'Get-HttpStatus')
    || !str_contains($data['test'], 'config/database.example.php')
    || !str_contains($data['test'], 'status') // no case requirement for HTTP status output
    || !str_contains($data['test'], '403,404')) {
    throw new RuntimeException('Falta verificación HTTP segura.');
}
foreach (['readiness.php --strict','RRHH2029','concurrencia','HTTPS','rol P4'] as $fragment) {
    if (!str_contains($data['manual'], $fragment)) {
        throw new RuntimeException('Manual de entrega al servidor incompleto: ' . $fragment);
    }
}
echo "[OK] Protección Apache, cuentas de producción, backup, HTTPS y checklist verificados.\n";
