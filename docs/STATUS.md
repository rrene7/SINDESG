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


## Ajuste de flujo — catálogo y recepción

Para evitar que una recepción quede bloqueada por ausencia de variantes:

- todo artículo nuevo crea automáticamente una primera variante stockable;
- talla, marca y códigos externos pueden quedar vacíos;
- el código interno de la variante siempre lo genera SINDESG;
- desde Recepción, si todavía no existen variantes, el usuario puede crear el primer artículo y volver automáticamente al formulario de recepción.

Esto respeta el proceso real: un equipo puede llegar sin código, sin marca o sin talla definida y aun así debe poder ser identificado y recibido por el sistema.


## Fase 4

**Producción interna — implementada.**

Incluye:
- órdenes de producción en borrador;
- código automático `OPR-######`;
- lote interno automático `PLT-######`;
- fecha/hora de producción;
- responsable;
- productos terminados por variante;
- insumos consumidos opcionales;
- validación del stock disponible en el Almacén Central;
- confirmación transaccional;
- movimientos `OUT` para insumos;
- movimientos `IN` para productos terminados;
- prevención de doble contabilización con `source_key`.

Guardar el borrador no altera el inventario. Al confirmar, SINDESG primero valida los insumos y luego registra toda la operación de forma atómica.

### Regla de materiales

Los insumos son opcionales porque no toda confección necesita tener materiales detallados desde el inicio del sistema. Cuando se registran, la orden no puede confirmarse si algún insumo supera la existencia disponible en el Almacén Central.

## Próximo bloque

**Fase 5 — Integración RRHH2029.**

Se habilitará la consulta de funcionarios y estructura territorial oficial en modo de solo lectura, sin duplicar la fuente maestra.
