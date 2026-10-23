<?php
declare(strict_types=1);

/*
 * Copie este archivo como config/local.php en el servidor.
 * config/local.php está excluido de Git.
 */
return [
    'environment' => 'production',
    // 'auto' detecta /SINDESG en el XAMPP local y / en Linux con public/.
    // Si un proxy publica la aplicación bajo un prefijo, indique p. ej. /logistica.
    'base_url' => 'auto',

    // Active solo cuando el sitio se publique exclusivamente por HTTPS.
    'session_secure' => true,
];
