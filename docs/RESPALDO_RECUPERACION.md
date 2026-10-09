# Fase 14B — Respaldo y recuperación

## Objetivo

Proteger SINDESG antes de su puesta en producción mediante respaldos verificables y una restauración controlada.

El respaldo cubre exclusivamente:

- base de datos `sindesg`;
- archivos de `storage/uploads`;
- manifiesto de versión y esquema;
- hash SHA-256 del dump y del archivo de uploads.

**RRHH2029 no se respalda ni se restaura desde SINDESG.**

## Crear un respaldo

Desde la raíz del proyecto:

```powershell
cd C:\xampp\htdocs\SINDESG
& "C:\xampp\php\php.exe" .\scripts\backup.php
```

El paquete se guarda por defecto en:

```text
storage\backups\sindesg_YYYYMMDD_HHMMSS
```

Contenido:

```text
sindesg.sql
uploads.zip
manifest.json
BACKUP_COMPLETE
```

Se puede usar un destino alterno:

```powershell
& "C:\xampp\php\php.exe" .\scripts\backup.php --destination="D:\Respaldos\SINDESG"
```

Y una etiqueta:

```powershell
& "C:\xampp\php\php.exe" .\scripts\backup.php --label=antes_actualizacion
```

## Verificar un respaldo sin restaurar

```powershell
& "C:\xampp\php\php.exe" .\scripts\restore.php --source="C:\ruta\al\respaldo" --verify-only
```

La verificación exige:

- `manifest.json`;
- marcador `BACKUP_COMPLETE`;
- nombre de base `sindesg`;
- coincidencia SHA-256 de `sindesg.sql`;
- coincidencia SHA-256 de `uploads.zip`.

## Restauración completa

La restauración requiere confirmación explícita:

```powershell
& "C:\xampp\php\php.exe" .\scripts\restore.php `
  --source="C:\ruta\al\respaldo" `
  --confirm=RESTORE-SINDESG
```

Antes de restaurar, el script crea automáticamente un respaldo del estado actual con etiqueta `pre_restore`.

Después:

1. restaura únicamente la base `sindesg`;
2. reemplaza `storage/uploads` usando un staging temporal;
3. ejecuta `scripts/readiness.php`;
4. aborta si la verificación posterior detecta un bloqueo.

## Uso de `--skip-safety-backup`

Existe únicamente para escenarios de desastre donde el estado actual no puede respaldarse:

```powershell
& "C:\xampp\php\php.exe" .\scripts\restore.php `
  --source="C:\ruta\al\respaldo" `
  --confirm=RESTORE-SINDESG `
  --skip-safety-backup
```

No debe utilizarse en una restauración normal.

## Credenciales

Los scripts usan `config/database.php` y generan un archivo temporal de opciones para MySQL. La contraseña no se coloca en la línea de comandos.

`config/database.php` sigue excluido de Git.

## Código fuente

El código fuente no se duplica dentro del paquete de respaldo. GitHub sigue siendo la fuente de verdad del código.

El `manifest.json` registra, cuando Git está disponible, el commit exacto asociado al respaldo.

## Política recomendada

Antes de producción:

- respaldo diario de `sindesg` + uploads;
- copia adicional fuera del servidor;
- prueba de restauración periódica;
- conservar al menos un respaldo previo a cada actualización;
- nunca incluir `rrhh2029` en los procedimientos de restauración de SINDESG.
