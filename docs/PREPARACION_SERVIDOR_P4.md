# SINDESG — Preparación para servidor institucional multiusuario

**Estado: pendiente de validación en el servidor real.** La instalación en
C:\xampp\htdocs\SINDESG es el piloto local, no el servidor institucional.

## Modelo de trabajo

Una sola instalación, una base SINDESG, usuarios individuales y almacenes
independientes. Almacén Central revisa y distribuye. Los P4 de Direcciones y
Zonas realizan solicitudes, recepciones y entregas **solo** en el almacén que
les autoriza el administrador.

**Nota real del código:** asignar un almacén P4 no asigna el rol por sí solo.
Actualmente hay que asignar **rol P4 + almacén P4** al usuario.

## Requisitos para TI

- Servidor Windows/Apache de red institucional, capacidad concurrente y disco
  suficiente; acceso limitado por red institucional o VPN.
- PHP 8.2+, extensiones PDO MySQL, mbstring, json, DOM, fileinfo y ZIP; MariaDB
  con base sindesg en utf8mb4, MySQL CLI y mysqldump; Git y PowerShell.
- Certificado HTTPS confiable, DNS institucional, sesión secure habilitada.
- Apache 2.4 con mod_rewrite y AllowOverride All. El proyecto actual usa
  rutas /SINDESG/public/assets y /SINDESG/storage/uploads. No cambiar
  DocumentRoot sin adaptar primero esas rutas.
- Dos usuarios DB diferentes, con contraseñas no vacías y no root: uno para
  la base sindesg y otro con SELECT-only sobre RRHH2029. Además, configurar
  rrhh.read_only = true.
- Configuración privada fuera de Git. Solo storage runtime debe ser escribible;
  respaldos cifrados fuera de la carpeta publicada, con restauración ensayada.
- Auditar archivos de uploads: ningún documento personal o sensible debe quedar
  descargable por URL directa sin una decisión explícita de control de acceso.

## De local a servidor

1. Confirmar main + CI verdes y árbol local sin cambios.
2. Hacer un respaldo **cuando se autorice la operación** en la computadora local:

       cd C:\xampp\htdocs\SINDESG
       & "C:\xampp\php\php.exe" .\scripts\backup.php --label=pre_servidor

   Conservar la carpeta completa identificada por BACKUP_PATH y sus hashes.
   Copiarla solo por un medio autorizado, nunca a GitHub. No se respalda RRHH2029.
3. En el servidor nuevo, obtener un checkout separado en
   C:\deploy-sindesg\SINDESG. Leer docs/DESPLIEGUE_INSTITUCIONAL.md.
4. Crear archivos config/database.php y config/local.php privados en el
   servidor (o en C:\sindesg-secure para el despliegue). En local.php configurar
   environment=production, base_url=/SINDESG y session_secure=true.
5. Para la instalación inicial, usar deploy-windows.ps1 -Production -Bootstrap
   con DatabaseConfigSource y LocalConfigSource. No usar bootstrap para
   actualizar un sistema activo. No usar SkipBackup en producción.
6. Verificar el respaldo de SINDESG, **antes** de restaurar:

       & "C:\xampp\php\php.exe" .\scripts\restore.php --source="<RESPALDO>" --verify-only

   La restauración verdadera solo se ejecuta en el **servidor nuevo**,
   autorizada por TI y con respaldo propio, mediante la confirmación
   RESTORE-SINDESG; nunca restaurar sobre el piloto local.
7. Comprobar las exigencias de publicación en el servidor:

       & "C:\xampp\php\php.exe" .\scripts\check.php --database
       & "C:\xampp\php\php.exe" .\scripts\readiness.php --strict

   No publicar si hay WARN o ERROR. La presencia de .htaccess no demuestra que
   Apache lo aplica: falta verificar por HTTPS desde otro equipo.
8. Desde otra computadora autorizada y con URL real HTTPS, ejecutar:

       powershell.exe -NoProfile -File .\scripts\verify-http-protection.ps1 -BaseUrl "https://HOST-INSTITUCIONAL/SINDESG"

   Exige CSS público HTTP 200 y rutas privadas HTTP 403/404. Nunca omitir la
   verificación del certificado TLS.
9. Validar usuario ADMIN, CENTRAL, P4 de Dirección y P4 de Zona con almacenes
   distintos. Comprobar acceso autorizado y denegado entre P4, trazabilidad,
   firmas y reportes. No generar inventario ficticio en producción.
10. Ensayar dos o más sesiones reales de MariaDB concurrentes y comprobar
    bloqueos, no duplicidad y kardex. Las pruebas SQLite del CI son insuficientes
    para dar por aprobada la concurrencia del servidor.
11. Documentar responsables operativos, respaldo externo, restauración en un
    entorno aislado, monitoreo, actualizaciones y rollback de código.

## Control final

Se requiere CI verde de main, HTTPS y configuración segura, protección HTTP
comprobada, readiness --strict verde, respaldo/restauración validados, cuentas
individuales y aislamiento, concurrencia real y aceptación de TI y operación.

**Hasta completar estas validaciones en el servidor, SINDESG sigue siendo
un piloto local con preparación de despliegue, no producción habilitada.**
