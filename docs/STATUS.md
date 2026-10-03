# Estado del proyecto SINDESG

## Fase 1

**Núcleo técnico y dashboard navegable — completada.**

## Fase 2A

**Catálogo maestro — completada.**

Incluye categorías, productos, variantes, tallas, marcas, códigos internos, fotografías y filtros.

### Regla de códigos de categoría

Los códigos de categoría son automáticos:

```text
CAT-000001
CAT-000002
CAT-000003
...
```

## Fase 2B

**Almacén Central, movimientos y existencias — completada.**

El stock se calcula desde `inventory_movements`, no desde saldos editables.

## Fase 3

**Recepción de lotes — implementada.**

Incluye:
- recepción en borrador;
- código automático `REC-######`;
- lote automático `LOT-######`;
- procedencia;
- proveedor/origen;
- documento soporte;
- fecha/hora;
- responsable que recibe;
- uno o varios renglones por variante;
- cantidad;
- observaciones;
- confirmación transaccional;
- movimiento `IN` al Almacén Central;
- actualización automática del kardex;
- `source_key` por renglón para evitar duplicidad;
- consulta de recepciones y detalle del lote.

### Regla de confirmación

Guardar un borrador no modifica inventario.

Al confirmar:

```text
Recepción borrador
→ bloqueo transaccional
→ movimientos IN por cada renglón
→ lote recibido
→ recepción confirmada
→ stock recalculado
```

Si una confirmación ya fue procesada, volver a intentarla no duplica los movimientos.

### Códigos

El usuario no introduce códigos de recepción ni de lote. SINDESG los genera usando IDs permanentes:

```text
REC-000001
LOT-000001
```

## Próximo bloque

**Fase 4 — Producción interna.**

Se modelará la confección de uniformes y otros artículos elaborados dentro de Servicios Generales, incluyendo producto terminado e insumos cuando aplique.
