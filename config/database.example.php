<?php
declare(strict_types=1);

/*
 | Copie este archivo como config/database.php para personalizar su entorno.
 | config/database.php está excluido de Git.
 |
 | Los valores siguientes coinciden con una instalación XAMPP local típica,
 | pero deben ajustarse si su MariaDB utiliza otras credenciales.
 */
return [
    'sindesg' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'sindesg',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'rrhh' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'rrhh2029',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'read_only' => true,
    ],
];
