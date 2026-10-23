# Incidencias presenciales de dotación · Fase 15 P4

El funcionario se presenta en su P4. El almacenista autenticado abre una **entrega confirmada** y registra el caso: devolución, cambio de talla, deterioro, reposición, pérdida reportada u otro. El sistema obtiene servidor-lado el almacén autorizado, el beneficiario, el artículo y la talla. Requiere motivo, cantidad y, en devoluciones/cambios/deterioros, constatación física y condición de la prenda.

El reporte genera folio `INC-######`, estado **Por revisar**, autor y evento auditable. **No mueve inventario.** El lote o dotación original, firmas y kardex no se editan. Se impide abrir un caso por cantidad superior al disponible para incidencias, descontando devoluciones confirmadas y otros expedientes activos del mismo renglón.

Central consulta las incidencias en `?module=incidents` y cambia de **Por revisar** a **En revisión**. Para artículos devueltos o cambios, usa el módulo **Ajustes** existente, cuyo borrador únicamente puede preparar Central/ADMIN y cuya confirmación corresponde a un administrador distinto del creador. ADMIN resuelve el expediente únicamente con un ajuste **confirmado, de la misma entrega, P4, tipo y cantidad**. En deterioro se exige devolución **no reutilizable**. En pérdida y casos de mera investigación, no se descuenta otra vez la prenda ya entregada.

Se conserva historial aditivo en `p4_incident_events`. Solo ADMIN puede cerrar `resuelto` o `no procedente` (resolución motivada). La apertura/revisión no implica devolución a stock y no altera RRHH2029.

## Activación de datos
La aplicación requiere ejecutar la migración **`database/schema/023_p4_incident_intake.sql`** con credenciales DBA **únicamente sobre SINDESG**, después de respaldo y restauración de prueba y con autorización de infraestructura. No se debe ejecutar automáticamente desde una petición web ni sobre RRHH2029. La migración crea dos tablas nuevas y un registro en `schema_migrations`; no modifica kardex ni entregas históricas.

Instalador CLI seguro (compatible con saltos LF y CRLF del archivo SQL):
```powershell
& "C:\xampp\php\php.exe" scripts/install_p4_incidents.php --check
& "C:\xampp\php\php.exe" scripts/install_p4_incidents.php --apply --confirm=APLICAR-023 "--backup=E:\SINDESG\storage\backups\<carpeta_del_respaldo_verificado>"
```

El instalador exige credenciales locales privadas, conexión exclusivamente a `sindesg`, dependencias y claves compatibles, respaldo íntegro SHA-256, esquema no iniciado y confirmación explícita. Rechaza estados parcialmente aplicados y no ejecuta borrados ni cambios en entregas, stock o RRHH2029. DDL no se revierte automáticamente; si hay errores se revisa manualmente.

Verificación: `php tests/p4_incident_intake_test.php`, `php tests/p4_incident_migration_installer_test.php`, pruebas de roles y CI Linux/Windows, y después prueba controlada con usuarios P4, Central y ADMIN. El proyecto admite Windows y Linux mediante el resolvedor de rutas existente.
