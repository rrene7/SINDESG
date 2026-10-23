<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Support/WebPaths.php';

use App\Support\WebPaths;

$check = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
};
$win = WebPaths::fromServer([
    'SCRIPT_FILENAME' => 'C:\\xampp\\htdocs\\SINDESG\\index.php',
    'SCRIPT_NAME' => '/SINDESG/index.php'
], 'C:\\xampp\\htdocs\\SINDESG');
$check($win->basePath === '/SINDESG' && !$win->publicDocumentRoot
    && $win->url('?module=requests&action=show&id=2') === '/SINDESG/?module=requests&action=show&id=2'
    && $win->asset('css/app.css') === '/SINDESG/public/assets/css/app.css'
    && $win->url('public/api/employees.php') === '/SINDESG/public/api/employees.php'
    && $win->uploaded('products/img1.png') === '/SINDESG/storage/uploads/products/img1.png',
    'Windows/XAMPP debe preservar rutas actuales.');
$linux = WebPaths::fromServer([
    'SCRIPT_FILENAME' => '/srv/sindesg/public/index.php',
    'SCRIPT_NAME' => '/index.php',
    'HTTP_HOST' => 'untrusted.invalid',
    'HTTP_X_FORWARDED_HOST' => 'fake.invalid'
], '/srv/sindesg');
$check($linux->basePath === '' && $linux->publicDocumentRoot
    && $linux->url('?module=requests') === '/?module=requests'
    && $linux->asset('css/app.css') === '/assets/css/app.css'
    && $linux->url('public/api/employees.php') === '/api/employees.php'
    && $linux->url('api/units.php') === '/api/units.php'
    && $linux->uploaded('articles/botas.png') === '/storage/uploads/articles/botas.png',
    'Linux con DocumentRoot public debe usar rutas de raíz sin /SINDESG ni /public.');
$subdir = WebPaths::fromServer([
    'SCRIPT_FILENAME' => '/srv/sindesg/public/index.php',
    'SCRIPT_NAME' => '/logistica/index.php',
], '/srv/sindesg');
$check($subdir->basePath === '/logistica'
    && $subdir->asset('js/app.js') === '/logistica/assets/js/app.js',
    'Linux en subdirectorio debe respetar el prefijo del servidor.');
$api = WebPaths::fromServer([
    'SCRIPT_FILENAME' => '/srv/sindesg/public/api/employees.php',
    'SCRIPT_NAME' => '/api/employees.php',
], '/srv/sindesg');
$check($api->url() === '/' && $api->url('public/api/units.php') === '/api/units.php',
    'API pública ejecutada directamente debe resolver el mismo prefijo.');
$override = WebPaths::fromServer([
    'SCRIPT_FILENAME' => '/srv/sindesg/public/index.php',
    'SCRIPT_NAME' => '/index.php',
], '/srv/sindesg', '/gestion');
$check($override->url('?module=dashboard') === '/gestion/?module=dashboard'
    && $override->asset('css/app.css') === '/gestion/assets/css/app.css',
    'Una configuración explícita de reverse proxy debe prevalecer.');
$root = WebPaths::fromServer([
    'SCRIPT_FILENAME' => '/srv/sindesg/public/index.php',
    'SCRIPT_NAME' => '/index.php',
], '/srv/sindesg', '/');
$check($root->basePath === '' && $root->url() === '/',
    'El valor / debe representar la raíz web.');
foreach (['https://evil.invalid', '//evil.invalid', '/../../privado', '/a/../b'] as $bad) {
    try {
        WebPaths::fromServer([], '/srv/sindesg', $bad);
        throw new RuntimeException('Se admitió base_url peligrosa: ' . $bad);
    } catch (InvalidArgumentException $valid) {}
}
$linuxScript = (string) file_get_contents(dirname(__DIR__) . '/deploy/linux/nginx-sindesg.conf.example');
$check(str_contains($linuxScript, 'root /srv/sindesg/public')
    && str_contains($linuxScript, 'fastcgi_pass')
    && str_contains($linuxScript, 'location /storage/uploads/ { return 404; }')
    && !str_contains($linuxScript, 'root /srv/sindesg;'),
    'La plantilla Linux no debe publicar el directorio privado.');
$bootstrap = (string) file_get_contents(dirname(__DIR__) . '/app/bootstrap.php');
$check(str_contains($bootstrap, 'WebPaths::fromServer')
    && str_contains($bootstrap, '$webPaths->url($path)')
    && str_contains($bootstrap, '$webPaths->asset($asset)'),
    'Las rutas reales del proyecto deben usar el resolvedor.');
echo "[OK] Windows/XAMPP, Linux raíz/subruta, API, archivos, URL segura y Nginx public-only.\n";
