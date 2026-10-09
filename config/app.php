<?php
declare(strict_types=1);

$config = [
    'name' => 'SINDESG',
    'full_name' => 'Sistema de Inventario y Despacho de Servicios Generales',
    'institution' => 'Policía Nacional de Panamá',
    'base_url' => '/SINDESG',
    'version' => '0.14.0-d-rc1',
    'environment' => 'local',
    'timezone' => 'America/Panama',
    'session_secure' => false,
];

$localFile = __DIR__ . '/local.php';
if (is_file($localFile)) {
    $local = require $localFile;

    if (!is_array($local)) {
        throw new RuntimeException(
            'config/local.php debe devolver un arreglo de configuración.'
        );
    }

    $config = array_replace($config, $local);
}

return $config;
