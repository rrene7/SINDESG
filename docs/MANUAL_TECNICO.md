# Manual técnico de SINDESG

**Sistema de Inventario y Despacho de Servicios Generales**

Versión documental: Fase 14C

## 1. Propósito

Este manual describe cómo instalar, validar, mantener, respaldar, recuperar y diagnosticar SINDESG. Está dirigido al personal técnico responsable de Apache/XAMPP, PHP, MariaDB, Git y soporte de la aplicación.

SINDESG administra su propia base `sindesg`. La base `rrhh2029` es una fuente externa de consulta y debe mantenerse en **solo lectura** desde SINDESG.

## 2. Arquitectura

```text
Navegador
  ↓
Apache / XAMPP
  ↓
PHP SINDESG
  ├─ Controllers
  ├─ Services
  ├─ Repositories
  ├─ Views
  └─ Support
       ├─ MariaDB: sindesg    (lectura/escritura)
       └─ MariaDB: rrhh2029   (solo lectura)
```

El inventario operativo es basado en movimientos. No debe corregirse un saldo alterando números manualmente ni eliminando movimientos consumados.

## 3. Requisitos

- Windows con XAMPP para el entorno institucional previsto.
- Apache.
- MariaDB/MySQL compatible con el esquema del proyecto.
- PHP 8.2 o superior.
- Extensiones PHP: `pdo`, `pdo_mysql`, `mbstring`, `json`, `dom`, `fileinfo`, `zip`.
- `proc_open` habilitado para respaldo/restauración.
- herramientas `mysql` y `mysqldump`.
- Git para actualización controlada del código.

Ruta local esperada:

```text
C:\xampp\htdocs\SINDESG
```

## 4. Archivos de configuración

### 4.1 Aplicación

`config/app.php` define, entre otros:

- nombre de la aplicación;
- URL base;
- versión;
- entorno;
- zona horaria.

La zona horaria esperada es:

```text
America/Panama
```

En desarrollo puede mantenerse `environment => local`. Para producción debe utilizarse la configuración institucional definida en Fase 14D.

### 4.2 Base de datos

Las credenciales reales deben estar en:

```text
config/database.php
```

Este archivo está excluido de Git.

Debe existir una conexión `sindesg` con permisos de operación sobre la base propia y una conexión `rrhh` declarada con:

```php
'read_only' => true
```

La cuenta usada contra RRHH2029 en producción debe limitarse a `SELECT` sobre los objetos autorizados.

## 5. Estructura principal

```text
app/                 lógica de aplicación
config/              configuración versionada y configuración local excluida
database/schema/     migraciones SQL 001–018
database/seeds/      fuentes controladas del catálogo histórico
docs/                documentación
public/              punto de entrada y recursos públicos
scripts/             verificación, respaldo y recuperación
storage/uploads/     archivos cargados por usuarios
storage/logs/        logs
storage/cache/       caché local
storage/backups/     respaldos locales
tests/               pruebas de regresión
```

## 6. Instalación y actualización del código

GitHub `rrene7/SINDESG` es la fuente de verdad.

Actualización local estándar:

```powershell
cd C:\xampp\htdocs\SINDESG
git pull --ff-only origin main
& "C:\xampp\php\php.exe" .\scripts\check.php --database
git log -1 --oneline
```

No se deben aplicar cambios locales improvisados si el cambio pertenece al código fuente. El flujo esperado es GitHub → CI verde → `main` → actualización local.

## 7. Migraciones de base de datos

Los scripts versionados están en `database/schema/` y actualmente abarcan `001` a `018`.

Una migración nueva debe:

1. ser versionada en Git;
2. ser validada por CI;
3. ser aplicada explícitamente en el entorno objetivo;
4. registrar su nombre en `schema_migrations`;
5. actualizar `system_settings.schema_version` cuando corresponda.

No se debe reaplicar a ciegas un SQL destructivo ni ejecutar scripts sobre `rrhh2029`.

## 8. Verificación estructural

Comando:

```powershell
& "C:\xampp\php\php.exe" .\scripts\check.php --database
```

Valida archivos requeridos y disponibilidad de conexiones.

## 9. Verificación integral de preparación

Comando:

```powershell
& "C:\xampp\php\php.exe" .\scripts\readiness.php
```

Revisa:

- versión de PHP;
- extensiones;
- configuración;
- directorios escribibles;
- secretos excluidos de Git;
- migraciones 001–018;
- conexión `sindesg`;
- conexión `rrhh`;
- versión del esquema;
- charset;
- contrato RRHH solo lectura;
- herramientas `mysql` / `mysqldump`;
- capacidad de respaldo/restauración.

`--strict` convierte advertencias en bloqueos y debe reservarse para una validación final de producción.

## 10. Seguridad

Roles de sistema definidos:

- `ADMIN`: control administrativo completo;
- `CENTRAL`: operación integral del Almacén Central;
- `P4`: operación asociada a solicitudes, recepción P4 y despacho;
- `AUDITOR`: consulta, reportes y auditoría;
- `VIEWER`: consulta sin operaciones de modificación.

Permisos principales:

`dashboard.view`, `inventory.view/manage`, `receipts.view/manage`, `production.view/manage`, `people.view`, `warehouses.view/manage`, `requests.view/manage`, `distribution.view/manage`, `deliveries.view/manage`, `adjustments.view/manage`, `reports.view`, `audit.view`, `admin.manage`.

Las contraseñas se almacenan mediante hash. Los valores de contraseña no deben aparecer en logs ni auditoría.

## 11. Auditoría

Las acciones sensibles se almacenan en `audit_logs` con actor, módulo, acción, registro, detalles, valores antes/después cuando aplica, IP, agente de usuario y fecha.

Las operaciones POST también se registran de forma sanitizada. Campos de contraseña y token CSRF se excluyen.

## 12. Integración RRHH2029

RRHH2029 es fuente maestra para personal, posición, rango, estado y unidad. SINDESG no debe crear ni modificar esos datos.

Reglas técnicas:

- consultar por identificadores técnicos;
- no emparejar personas por nombre libre;
- no escribir en RRHH2029;
- conservar snapshots históricos en entregas;
- utilizar `rrhhRead()` o la capa de lectura equivalente.

## 13. Inventario y consistencia

La existencia se deriva de movimientos. Los flujos que afectan stock deben producir movimientos auditables:

- recepción;
- producción;
- transferencias;
- despacho individual;
- devolución;
- ajuste compensatorio.

Una transferencia Central → P4 reduce el origen y aumenta el destino, sin reducir el total institucional.

## 14. Migración histórica

La Fase 13 quedó cerrada mediante:

- 13A: staging seguro;
- 13B: catálogo histórico normalizado;
- 13C: conciliación documentada;
- 13D: cierre controlado.

Los históricos cerrados no se convierten automáticamente en kardex vivo. El Control de inventario quedó archivado como snapshot y las hojas de ENTRADAS/SALIDAS/rendiciones se conservan como referencias.

## 15. Respaldo

Crear respaldo:

```powershell
& "C:\xampp\php\php.exe" .\scripts\backup.php
```

El paquete contiene:

- `sindesg.sql`;
- `uploads.zip`;
- `manifest.json`;
- `BACKUP_COMPLETE`.

Se registra SHA-256 para dump y uploads. RRHH2029 no se incluye.

Ver documentación completa: `docs/RESPALDO_RECUPERACION.md`.

## 16. Verificación de respaldo

```powershell
& "C:\xampp\php\php.exe" .\scripts\restore.php --source="C:\ruta\respaldo" --verify-only
```

Esta operación no restaura datos; solo verifica manifiesto, marcador y SHA-256.

## 17. Restauración

La restauración requiere confirmación explícita:

```powershell
& "C:\xampp\php\php.exe" .\scripts\restore.php `
  --source="C:\ruta\respaldo" `
  --confirm=RESTORE-SINDESG
```

Antes de restaurar se crea un respaldo `pre_restore`. El dump es inspeccionado para impedir restaurar contenido asociado a `rrhh2029`.

## 18. Diagnóstico de incidencias

Orden recomendado:

1. confirmar Apache y MariaDB;
2. ejecutar `scripts/check.php --database`;
3. ejecutar `scripts/readiness.php`;
4. revisar el error visible y el módulo afectado;
5. verificar `config/database.php` sin compartir credenciales;
6. revisar auditoría si la operación llegó a ejecutarse;
7. revisar commit de `main` y CI antes de modificar código;
8. corregir en rama, validar CI y fusionar;
9. actualizar local con `git pull --ff-only`.

## 19. Incidencias comunes

### SINDESG no conecta

Verificar MariaDB, base `sindesg` y `config/database.php`.

### Funcionarios no cargan

Verificar conexión RRHH, permiso de lectura y objetos autorizados. No solucionar otorgando permisos de escritura.

### No se puede cargar un archivo

Verificar `storage/uploads`, permisos de escritura, extensión y límites PHP/Apache.

### Respaldo falla

Ejecutar `readiness.php`; verificar extensión ZIP, `proc_open`, `mysqldump`, credenciales y escritura en `storage/backups`.

### Una operación de inventario parece incorrecta

No editar saldos ni borrar movimientos. Revisar el kardex y aplicar el flujo de ajuste autorizado.

## 20. Cierre técnico antes de producción

Antes de 14D debe quedar confirmado:

- CI de `main` verde;
- `check.php --database` sin errores;
- `readiness.php` sin bloqueos;
- respaldo real creado y verificado;
- conexión RRHH restringida a lectura;
- manuales actualizados;
- procedimiento de actualización y recuperación aprobado;
- prueba funcional de los módulos críticos.

El despliegue institucional definitivo se ejecutará en Fase 14D.
