# Despliegue institucional de SINDESG

## 1. Estado

Fase 14D prepara el despliegue institucional de SINDESG sobre Windows/XAMPP.

La versión `0.14.0-d-rc1` es **release candidate**. No debe marcarse como versión final de producción hasta validar el sistema en el servidor institucional real.

## 2. Principios

- GitHub `rrene7/SINDESG` es la fuente de verdad del código.
- La configuración real no se versiona.
- `config/database.php` y `config/local.php` se preservan al actualizar.
- `storage/uploads`, `storage/backups`, `storage/logs` y `storage/cache` no se reemplazan durante una actualización normal.
- Antes de actualizar un destino existente se crea un respaldo `pre_deploy`.
- RRHH2029 se utiliza únicamente en lectura.
- Un despliegue de producción debe terminar con `readiness.php --strict` en verde.

## 3. Arquitectura recomendada del servidor

Separar el checkout Git del directorio publicado:

```text
C:\deploy-sindesg\SINDESG      ← checkout Git
C:\xampp\htdocs\SINDESG       ← aplicación publicada
```

Esto permite actualizar y validar el código antes de sincronizarlo al webroot.

## 4. Configuración privada

### Base de datos

Crear en el servidor:

```text
C:\xampp\htdocs\SINDESG\config\database.php
```

basándose en `config/database.example.php`.

La conexión `sindesg` debe operar exclusivamente sobre la base propia. La conexión `rrhh` debe declarar `read_only => true` y usar una cuenta con permisos de lectura.

### Configuración de aplicación

Crear:

```text
C:\xampp\htdocs\SINDESG\config\local.php
```

basándose en `config/local.example.php`.

Ejemplo:

```php
<?php
declare(strict_types=1);

return [
    'environment' => 'production',
    'base_url' => '/SINDESG',
    'session_secure' => true,
];
```

`session_secure => true` requiere publicar el sitio por HTTPS. Si la infraestructura institucional aún no dispone de HTTPS, esa condición debe resolverse antes de aprobar el preflight estricto.

## 5. Instalación inicial de código

Clone el repositorio en el checkout de despliegue:

```powershell
cd C:\deploy-sindesg
git clone https://github.com/rrene7/SINDESG.git
cd C:\deploy-sindesg\SINDESG
git checkout main
git pull --ff-only origin main
```

Prepare fuera del repositorio los dos archivos privados:

```text
C:\sindesg-secure\database.php
C:\sindesg-secure\local.php
```

Despliegue inicial de código:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\deploy-windows.ps1 `
  -Destination "C:\xampp\htdocs\SINDESG" `
  -PhpExe "C:\xampp\php\php.exe" `
  -DatabaseConfigSource "C:\sindesg-secure\database.php" `
  -LocalConfigSource "C:\sindesg-secure\local.php" `
  -Production `
  -Bootstrap
```

`-Bootstrap` copia el código y preserva/configura los archivos privados, pero no exige que la base ya esté restaurada.

## 6. Migrar el estado actual al servidor nuevo

La forma recomendada es trasladar un respaldo verificado creado por Fase 14B.

En el entorno fuente:

```powershell
& "C:\xampp\php\php.exe" .\scripts\backup.php --label=produccion_inicial
```

Copie la carpeta completa del respaldo al servidor institucional por un medio autorizado.

Verifique primero:

```powershell
cd C:\xampp\htdocs\SINDESG
& "C:\xampp\php\php.exe" .\scripts\restore.php `
  --source="D:\Respaldos\SINDESG\sindesg_..." `
  --verify-only
```

Restaure:

```powershell
& "C:\xampp\php\php.exe" .\scripts\restore.php `
  --source="D:\Respaldos\SINDESG\sindesg_..." `
  --confirm=RESTORE-SINDESG
```

RRHH2029 no forma parte de ese respaldo.

## 7. Preflight final de producción

```powershell
cd C:\xampp\htdocs\SINDESG
& "C:\xampp\php\php.exe" .\scripts\check.php --database
& "C:\xampp\php\php.exe" .\scripts\readiness.php --strict
```

No se aprueba la puesta en servicio mientras `readiness.php --strict` termine con error.

## 8. Actualización normal

En el checkout Git:

```powershell
cd C:\deploy-sindesg\SINDESG
git fetch origin
git checkout main
git pull --ff-only origin main
git log -1 --oneline
```

Después:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\deploy-windows.ps1 `
  -Destination "C:\xampp\htdocs\SINDESG" `
  -PhpExe "C:\xampp\php\php.exe" `
  -Production
```

En un destino existente el script:

1. valida el checkout fuente;
2. crea un respaldo `pre_deploy`;
3. sincroniza código versionado;
4. preserva credenciales y configuración local;
5. preserva uploads/logs/cache/backups;
6. registra un marcador JSON de despliegue;
7. ejecuta `check.php --database`;
8. ejecuta `readiness.php --strict`.

## 9. Marcadores de despliegue

Se guardan en:

```text
storage\deployments\deployment_YYYYMMDD_HHMMSS.json
```

Registran fecha, checkout fuente, destino, commit Git cuando está disponible y ruta del respaldo `pre_deploy`.

## 10. Rollback de código

Si una versión nueva debe retirarse:

1. identificar el commit anterior aprobado;
2. preparar ese commit en el checkout de despliegue;
3. ejecutar nuevamente `deploy-windows.ps1 -Production`;
4. verificar `readiness.php --strict`.

Ejemplo conceptual:

```powershell
cd C:\deploy-sindesg\SINDESG
git fetch origin
git checkout <COMMIT_ANTERIOR_APROBADO>

powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\deploy-windows.ps1 `
  -Destination "C:\xampp\htdocs\SINDESG" `
  -PhpExe "C:\xampp\php\php.exe" `
  -Production
```

Después del rollback, vuelva el checkout a `main` antes de la próxima actualización.

## 11. Rollback de datos

Solo es necesario si una actualización produjo cambios de datos/esquema que deban revertirse.

Utilice el `PRE_DEPLOY_BACKUP` generado antes del despliegue:

```powershell
& "C:\xampp\php\php.exe" C:\xampp\htdocs\SINDESG\scripts\restore.php `
  --source="<PRE_DEPLOY_BACKUP>" `
  --confirm=RESTORE-SINDESG
```

La restauración crea además su propio respaldo de seguridad `pre_restore` antes de reemplazar datos.

## 12. Qué preserva el desplegador

Durante una actualización normal no se reemplazan:

- `config/database.php`;
- `config/local.php`;
- `storage/uploads`;
- `storage/backups`;
- `storage/logs`;
- `storage/cache`;
- `storage/deployments`.

## 13. Qué no debe hacerse

- desplegar directamente desde una rama de desarrollo;
- usar `git reset --hard` como procedimiento normal;
- copiar una base completa sin verificación;
- incluir RRHH2029 en respaldos/restauraciones de SINDESG;
- reemplazar `config/database.php` con un archivo del repositorio;
- borrar `storage/uploads` durante una actualización;
- aprobar producción con CI rojo o readiness estricto fallando;
- ejecutar `-SkipBackup` en una actualización rutinaria.

## 14. Validación funcional posterior

Después del preflight técnico deben probarse al menos:

- inicio/cierre de sesión;
- Dashboard;
- Inventario;
- consulta de funcionarios RRHH;
- recepción de prueba controlada o consulta de una existente;
- solicitudes;
- distribución;
- despacho individual;
- ajustes;
- reportes;
- auditoría;
- administración;
- consulta del histórico archivado.

No es necesario generar movimientos reales si la validación se realiza con registros ya existentes y consultas de solo lectura.

## 15. Criterio de cierre de Fase 14D

Fase 14D queda cerrada únicamente cuando:

- `main` y CI están verdes;
- el release candidate está desplegado en el servidor institucional;
- `check.php --database` pasa;
- `readiness.php --strict` pasa;
- respaldo y restauración fueron verificados;
- acceso web institucional fue probado;
- RRHH2029 fue confirmado como solo lectura;
- las pruebas funcionales críticas fueron aceptadas.

Hasta ese momento la versión mantiene la etiqueta **release candidate**.
