# SINDESG — publicación institucional en Linux (sin XAMPP)

> **Preparación del código, no despliegue ejecutado.** Se mantiene Windows/XAMPP como desarrollo y se publica Linux con Nginx + PHP-FPM + MariaDB/MySQL. No trasladar bases ni exponer el portal hasta aprobación de Ciberseguridad.

## 1. Directorios y rutas

Ubicación de ejemplo del checkout/aplicación en Linux:

```text
/srv/sindesg/
  app/                 privado
  config/              privado (local.php y database.php no versionados)
  database/            privado
  scripts/             privado
  storage/             privado, persistente y respaldado
    uploads/           solo las imágenes permitidas se publican con alias
  public/              ÚNICA raíz HTTP del servidor
    index.php          front controller
    api/               endpoints autenticados
    assets/            CSS / JS
```

La carpeta completa `/srv/sindesg` **no** debe ser el DocumentRoot. En Nginx, `root /srv/sindesg/public`. `index.php` del directorio raíz existe únicamente para compatibilidad de desarrollo con XAMPP.

El resolvedor `App\Support\WebPaths` calcula las rutas sin inspeccionar Host, sin usar rutas de Windows y sin fiarse de cabeceras `X-Forwarded-*`:

| Escenario | Ruta HTTP | URL de CSS | URL de API |
|---|---|---|---|
| Windows + XAMPP actual | `/SINDESG/` | `/SINDESG/public/assets/css/app.css` | `/SINDESG/public/api/employees.php` |
| Linux + Nginx, raíz | `/` | `/assets/css/app.css` | `/api/employees.php` |
| Linux en subruta `/logistica/` | `/logistica/` | `/logistica/assets/css/app.css` | `/logistica/api/employees.php` |

Para la subruta, el proxy deberá trasladar coherentemente el prefijo, sin publicar `app`, `storage`, `scripts` o `config`. Configure `base_url => '/logistica'` en `config/local.php` si `SCRIPT_NAME` no conserva la subruta.

## 2. Prerrequisitos Linux

- PHP **8.2 o superior** y PHP-FPM, módulos `pdo_mysql`, `mbstring`, `json`, `dom`, `fileinfo`, `zip`; MariaDB/MySQL con cuentas institucionales propias.
- Nginx, certificado TLS válido, DNS institucional, registro/auditoría y respaldo protegido.
- Cuenta de servicio sin privilegios administrativos. `storage/uploads`, `storage/logs`, `storage/cache` y `storage/backups` escribibles por el servicio. Ni el código fuente ni archivos privados deben ser escribibles desde la web.
- Ajuste el `fastcgi_pass` de la plantilla según el PHP-FPM instalado (ej. `/run/php/php8.2-fpm.sock`) y configure la raíz de publicación real.

`deploy/linux/nginx-sindesg.conf.example` es **una plantilla**, nunca una configuración de producción lista para aplicar sin revisar DNS, certificados, socket, rutas y reglas institucionales.

## 3. Configuración privada

**No versionar credenciales.** En el servidor cree `config/local.php` según `config/local.example.php`:

```php
<?php
return [
    'environment' => 'production',
    'base_url' => 'auto',   // o '/' para raíz, o '/logistica' si hay proxy
    'session_secure' => true, // HTTPS obligatorio
];
```

Defina `config/database.php` copiando solo el formato de `config/database.example.php`, con **dos cuentas separadas**: SINDESG con los permisos mínimos de operación y RRHH2029 **SELECT únicamente**, `read_only => true`. Nunca use `root` de MariaDB ni la cuenta vacía de ejemplo en producción.

El nombre del sistema operativo no modifica la política `environment`: `production` requiere configuración expresa, TLS y validación de seguridad; solo las rutas se detectan automáticamente. No introducir datos de usuarios ni números de identificación en `logs` HTTP.

## 4. Publicación segura y verificación

1. Prepare un checkout separado, valide el commit `main` y el CI en Linux y Windows. Guarde un respaldo protegido de código, configuración y estado SINDESG antes de actualizar un destino existente.
2. Copie o clone código sin sobrescribir `config/local.php`, `config/database.php` ni `storage/`. Ajuste propietario y permisos sin `chmod -R 777`.
3. Personalice la plantilla Nginx; use `nginx -t` y recargue solo después del resultado `syntax is ok`. Restrinja PHP a `index.php` y a los endpoints permitidos.
4. Compruebe la ruta de inicio, login, CSS/JS y APIs autenticadas. La petición no autenticada a una API debe devolver `401`, y la página debe conservar sus cookies protegidas HTTPS.
5. Desde una estación autorizada, ejecute `scripts/verify-http-protection.ps1 -BaseUrl https://sindesg.example.invalid` (adaptar DNS). Exija recursos públicos `200`, archivos privados `403/404` y ausencia de ejecución de scripts subidos.
6. Ejecute en el servidor `php scripts/check.php`, `php scripts/readiness.php --strict`. El validador comprueba preparación local; **no demuestra por sí solo** que el Nginx activo está aislando todos los directorios.
7. Verifique las cuentas con privilegios de solo lectura RRHH2029, el aislamiento P4/Central, firmas, transferencias, kardex, respaldos y restauración en entorno autorizado antes de habilitar producción.

Los archivos en `storage/uploads` pueden contener datos nominales. El ejemplo Nginx permite únicamente imágenes con nombres sencillos; los archivos Excel, PDF, CSV y ejecutables **no** se exponen como archivos estáticos. Si se requiere descargar documentos institucionales, debe utilizarse una ruta autenticada y autorizada. No agregue un alias abierto a toda la carpeta.

## 5. Regreso a Windows y rollback

El modo XAMPP conserva `/SINDESG/` y `public/assets`, manteniendo el script `deploy-windows.ps1` vigente. Una reversión en Linux debe volver a un commit aprobado, preservar configuración privada y `storage/`, y volver a ejecutar verificaciones. **Este cambio no requiere migraciones ni ejecuta SQL.**

> La puesta en producción efectiva queda sujeta a instalación, revisión de Ciberseguridad y pruebas contra el servidor Linux real.
