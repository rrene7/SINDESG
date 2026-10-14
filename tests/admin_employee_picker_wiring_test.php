<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$layout = (string) file_get_contents($root . '/app/Views/layout.php');
$form = (string) file_get_contents($root . '/app/Views/admin/user-form.php');
$js = (string) file_get_contents($root . '/public/assets/js/security.js');
$controller = (string) file_get_contents($root . '/app/Controllers/AdminController.php');
if (!str_contains($layout, "if (\$currentModule === 'admin')")
    || !str_contains($layout, "sindesg_asset('js/security.js')")
    || !str_contains($form, 'data-security-picker')
    || !str_contains($form, 'data-security-search')
    || !str_contains($controller, "public/api/employees.php")
    || !str_contains($js, "data-security-picker")
    || !str_contains($js, "fetch(endpoint")) {
    throw new RuntimeException('El buscador RRHH de Administración no está enlazado correctamente.');
}
echo "[OK] Administración carga el buscador de funcionarios y su endpoint RRHH.\n";
