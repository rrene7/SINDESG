<?php
declare(strict_types=1);

/*
 * Copie este archivo como config/local.php en el servidor.
 * config/local.php está excluido de Git.
 */
return [
    'environment' => 'production',
    'base_url' => '/SINDESG',

    // Active solo cuando el sitio se publique exclusivamente por HTTPS.
    'session_secure' => true,
];
