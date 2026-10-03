# Estado del proyecto SINDESG

## Fase 1

**Núcleo técnico y dashboard navegable — completada en GitHub.**

Incluye:
- estructura PHP;
- XAMPP/MariaDB;
- base propia `sindesg`;
- conexión RRHH preparada en solo lectura;
- dashboard responsive;
- navegación;
- búsqueda;
- CI.

## Bloque actual

**Fase 2A — Catálogo maestro de artículos.**

Incluye:
- categorías;
- productos;
- variantes;
- tallas dinámicas;
- marcas dinámicas;
- código interno SINDESG;
- código institucional/ISTMO opcional;
- código de proveedor opcional;
- fotografía principal;
- listado y filtros;
- detalle del artículo;
- formularios con CSRF;
- migración `002_catalog.sql`;
- pruebas estáticas del esquema.

## Importante

Todavía no se muestran cantidades de inventario ficticias.

El stock se implementará en **Fase 2B** mediante movimientos de inventario. Una cantidad no se editará manualmente como un saldo aislado.

## Fotografías

Las fotografías reales se cargan desde el formulario de artículo y se almacenan localmente en:

`storage/uploads/catalog/`

Los archivos cargados no se versionan en Git.

## Próximo bloque

**Fase 2B — Almacén Central, movimientos base y cálculo de existencias.**
