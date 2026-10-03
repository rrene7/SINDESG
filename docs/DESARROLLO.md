# Flujo de desarrollo y actualización local

## Principio de trabajo

El repositorio GitHub `rrene7/SINDESG` es la fuente de verdad del código.

La secuencia de trabajo será:

```text
Revisar main
→ desarrollar bloque
→ validar
→ publicar en GitHub
→ confirmar commit
→ actualizar copia local XAMPP
→ probar en navegador
```

No se trabajará haciendo cambios locales desconectados y luego tratando de reconstruir qué se modificó.

## Entorno local previsto

```text
C:\xampp\htdocs\SINDESG
```

Servicios:
- Apache de XAMPP.
- MariaDB de XAMPP.

Base propia:
- `sindesg`

Base externa de consulta:
- `rrhh2029`

## Primera instalación local

Cuando exista el primer bloque ejecutable:

```powershell
cd C:\xampp\htdocs
git clone https://github.com/rrene7/SINDESG.git
cd SINDESG
git status
git log -1 --oneline
```

Luego se configurará el archivo local de conexión según la plantilla versionada.

Las credenciales locales nunca se subirán al repositorio.

## Actualización después de cada bloque

Antes de actualizar:

```powershell
cd C:\xampp\htdocs\SINDESG
git status
```

Si el árbol está limpio:

```powershell
git pull --ff-only origin main
git log -1 --oneline
```

No se utilizará `git reset --hard` como procedimiento normal de actualización.

## Base de datos

Los cambios de esquema se almacenarán en scripts/migraciones versionados.

Nunca se dependerá de:
- recordar manualmente qué tabla se creó;
- editar producción sin script;
- importar una base completa para aplicar un cambio pequeño.

Cada cambio de base debe:
1. tener archivo versionado;
2. ser repetible o detectar que ya fue aplicado;
3. preservar datos existentes;
4. documentar cualquier paso manual imprescindible.

## Configuración

Se versionarán únicamente archivos de ejemplo:

```text
config/database.example.php
.env.example (si se adopta .env)
```

Se excluirán:
- contraseñas;
- claves;
- configuración real local;
- archivos temporales;
- logs;
- imágenes cargadas en producción si no forman parte del código fuente.

## Validaciones por bloque

Cuando el código PHP exista, cada bloque deberá validar al menos:

### PHP
```powershell
php -l ruta\archivo.php
```

y posteriormente un script que recorra todo el código PHP.

### Base de datos
- esquema aplica sin errores;
- FKs e índices esperados;
- operaciones críticas dentro de transacciones;
- prueba de rollback cuando corresponda.

### Funcional
- navegación;
- formularios;
- permisos;
- inventario antes/después;
- historial/auditoría.

## GitHub Actions

Cuando exista el primer código PHP ejecutable se incorporará CI para:
- lint PHP;
- pruebas automatizadas disponibles;
- validaciones adicionales que incorpore el proyecto.

No se dará un bloque por terminado si CI está rojo.

## Commits

Se usarán mensajes pequeños y descriptivos, por ejemplo:

```text
docs: define inventory roadmap
feat: add product catalog
feat: add lot receiving flow
fix: prevent duplicate inventory movement
test: cover warehouse transfer
```

## Bloques pequeños

No se debe desarrollar simultáneamente:
- catálogo;
- solicitudes;
- P4;
- despachos;
- reportes;
- auditoría.

Cada bloque debe poder probarse antes de continuar.

## Regla de seguridad RRHH

La integración de desarrollo utilizará permisos de lectura.

SINDESG jamás debe ejecutar desde su código:
- UPDATE de RRHH;
- DELETE de RRHH;
- INSERT de RRHH;
- procedimientos de modificación de estructura.

## Actualizaciones al usuario

Después de completar cada bloque se debe informar:
- commit exacto publicado;
- archivos principales modificados;
- validaciones ejecutadas;
- resultado;
- comando mínimo para actualizar XAMPP;
- qué debe probarse visualmente.
