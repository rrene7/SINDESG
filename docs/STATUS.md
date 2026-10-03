# Estado del proyecto SINDESG

## Fase 1

**Núcleo técnico y dashboard navegable — completada.**

Incluye:
- estructura PHP;
- XAMPP/MariaDB;
- base propia `sindesg`;
- conexión RRHH preparada en solo lectura;
- dashboard responsive;
- navegación;
- búsqueda;
- CI.

## Fase 2A

**Catálogo maestro — completada.**

Incluye:
- categorías;
- productos;
- variantes;
- tallas y marcas dinámicas;
- código SINDESG;
- códigos externos opcionales;
- fotografía principal;
- filtros y detalle;
- formularios con CSRF.

### Regla de códigos de categoría

Los códigos de categoría no son editables por el usuario.

SINDESG los genera en secuencia usando el ID permanente:

```text
CAT-000001
CAT-000002
CAT-000003
...
```

Esto conserva orden de creación, unicidad y trazabilidad. La migración `004_category_codes.sql` normaliza también las categorías ya existentes.

## Fase 2B

**Almacén Central, movimientos y existencias — completada.**

Incluye:
- tabla de almacenes;
- Almacén Central de Servicios Generales;
- kardex base por variante;
- movimientos con dirección entrada/salida;
- cantidad decimal para soportar materiales además de unidades;
- referencia documental;
- actor descriptivo;
- `source_key` para evitar duplicados en futuras importaciones;
- vista de stock por almacén y variante;
- vista de stock agregado por producto;
- stock institucional en el catálogo;
- stock Central/total por variante;
- movimientos recientes en el detalle de producto.

## Regla de inventario

SINDESG no guarda un saldo editable como fuente de verdad.

La existencia se obtiene de:

```text
SUM(entradas) - SUM(salidas)
```

sobre el historial de `inventory_movements`.

Una transferencia futura Central → P4 generará una salida en origen y una entrada en destino sin alterar el total institucional.

## Sin datos ficticios

La estructura crea el Almacén Central, pero no inserta movimientos artificiales.

Hasta que se registren recepciones o se migren históricos validados, el stock correcto es cero.

## Próximo bloque

**Fase 3 — Recepción de lotes.**

La recepción será la primera operación funcional que generará movimientos `IN` reales hacia el Almacén Central.
