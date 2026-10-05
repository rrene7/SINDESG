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

## Fase 5

**Integración RRHH2029 — implementada.**

Incluye:
- búsqueda de funcionarios por nombre, apellido, cédula o posición;
- ficha vigente con `employee_id`, cédula, posición, rango, estado y unidad;
- navegación de la estructura oficial vigente;
- consulta de subunidades;
- consulta de funcionarios asignados a una unidad;
- uso directo de RRHH2029 como fuente maestra;
- cero tablas espejo de personal o territorio en SINDESG;
- consultas exclusivamente de lectura.

Los cambios de personal o estructura realizados en RRHH2029 se reflejan al volver a consultar SINDESG.

## Fase 6

**Almacenes y P4 — implementada.**

Incluye:
- Almacén Central institucional;
- alta explícita de P4 desde una unidad vigente de RRHH2029;
- búsqueda de unidad por nombre o código;
- código P4 automático;
- responsable seleccionado desde RRHH2029;
- snapshot histórico de unidad y responsable;
- activación/inactivación sin borrar historial;
- existencia separada por almacén derivada del kardex;
- detalle de stock por artículo, variante, talla y marca;
- actualización del nombre actual de la unidad desde RRHH2029 sin sobrescribir el snapshot histórico.

Una unidad de RRHH no se convierte automáticamente en P4. La habilitación pertenece exclusivamente a SINDESG.

## Fase 7

**Solicitudes P4 → Almacén Central — implementada.**

Incluye:
- solicitud en borrador con código automático;
- P4 activo como origen;
- solicitante consultado desde RRHH2029;
- renglones por variante;
- beneficiario opcional por renglón para solicitudes nominales;
- cantidades solicitadas;
- envío;
- paso a revisión;
- aprobación total, parcial o rechazo;
- revisor consultado desde RRHH2029;
- snapshots de solicitante, beneficiario y revisor;
- validación de duplicidades;
- validación de stock central comprometible;
- reserva lógica de cantidades aprobadas aún no atendidas;
- sin movimientos físicos de inventario durante la aprobación.

### Regla contable

Aprobar una solicitud **no mueve inventario**. El movimiento Almacén Central → P4 ocurrirá en la Fase 8. La aprobación solamente compromete disponibilidad para evitar sobreaprobar el mismo stock.

## Fase 8

**Distribución Almacén Central → P4 — implementada.**

Incluye:
- preparación desde solicitudes aprobadas;
- código automático `TRF-######`;
- transferencias parciales;
- preparador, autorizador, funcionario que entrega y funcionario que recibe desde RRHH2029;
- documento/requisición;
- validación de stock al autorizar y al despachar;
- Almacén virtual `TRANSIT-SG`;
- despacho transaccional Central → Tránsito;
- recepción transaccional Tránsito → P4;
- movimientos con `source_key` para impedir duplicidades;
- cantidades despachadas y recibidas acumuladas por renglón de solicitud;
- solicitud `fulfilled` cuando todo lo aprobado llega al P4.

### Regla contable de transferencia

Durante el tránsito el inventario institucional no desaparece:

```text
Central 20
Despacha 2
Central 18 + Tránsito 2 = 20

P4 recibe 2
Central 18 + Tránsito 0 + P4 2 = 20
```

Una solicitud aprobada mantiene como reserva únicamente la cantidad aprobada que todavía no ha sido despachada.

## Próximo bloque

**Fase 9 — Despacho individual P4 → funcionario.**

El P4 entregará las prendas al funcionario final, tomando como base el beneficiario registrado en la solicitud cuando exista.


## Correcciones posteriores a la prueba local

Se corrigieron tres observaciones detectadas durante la validación:

1. **Recibido por** ahora se selecciona mediante búsqueda de funcionario en RRHH2029, no mediante texto libre. SINDESG conserva `employee_id` y un snapshot de cédula, posición, rango y unidad al momento de la recepción.
2. El **dashboard** dejó de usar valores demostrativos para catálogo/stock. Ahora consume fotografías, existencias, tallas y recepciones reales.
3. Se agregó una migración de reparación UTF-8 para categorías afectadas por la canalización de SQL en Windows PowerShell.

La consulta de funcionarios continúa siendo de solo lectura hacia RRHH2029.


## Ajuste de presentación RRHH

La consulta de funcionarios ahora separa explícitamente el contexto jerárquico:

- **Dirección**: se resuelve desde el ancestro vigente de tipo dirección.
- **Oficina**: se resuelve desde el ancestro vigente de tipo oficina.
- **Dependencia actual**: conserva la unidad exacta donde está adscrito el funcionario.

Ejemplo esperado para una adscripción en Gestión Estratégica:
`Dirección Nacional de Telemática → Oficina de Gestión Estratégica`.

El mismo contexto se muestra en la búsqueda de funcionarios y en el selector de **Recibido por** de Recepción.


## Ajustes posteriores a validación local de Fase 8

La prueba real confirmó la contabilidad esperada: **Central 18 + Tránsito 0 + P4 2 = 20 unidades institucionales**.

Se ajustó la presentación para reflejar correctamente esa realidad:
- `TRANSIT-SG` se identifica como **TRÁNSITO**, nunca como P4;
- el almacén de tránsito no muestra vínculo RRHH, responsable P4 ni controles de activar/inactivar P4;
- el dashboard denomina el total como **stock institucional** y muestra el desglose Central / P4 / Tránsito;
- se incorporó una reparación idempotente del nombre **Almacén Central de Servicios Generales** para instalaciones afectadas por codificación histórica.


## Ajuste de concepto de stock en dashboard

Después de validar la distribución Central → P4, el indicador principal del dashboard se redefinió para evitar confusión:

- **Stock disponible**: unidades libres en el Almacén Central para nuevas entregas.
- **Central físico**: existencia física actual del Almacén Central.
- **Comprometido**: unidades aprobadas para solicitudes pero todavía no despachadas.
- **P4**: unidades que ya están físicamente en los P4.
- **Tránsito**: unidades despachadas por el Central y aún no recibidas por el P4.
- **Institucional**: suma física Central + P4 + Tránsito.

La fórmula operativa es:

```text
Disponible = Central físico - Comprometido
Institucional = Central físico + P4 + Tránsito
```

Las tarjetas de categorías y las tallas disponibles ahora priorizan la disponibilidad real del Almacén Central, mientras el total institucional queda como información secundaria.


## Ajuste de disponibilidad en módulo Inventario

El módulo Inventario adopta la misma semántica operativa del dashboard:

- **Disponible** es el dato principal para decidir nuevas entregas.
- **Central físico** muestra la existencia real en el Almacén Central.
- **Comprometido** representa aprobación pendiente de despacho.
- **P4** y **Tránsito** muestran dónde se encuentra el resto del inventario institucional.
- **Institucional** permanece como total físico de la institución.

La tarjeta de cada artículo ya no usa el total institucional bajo la etiqueta genérica "Existencia". Ahora muestra **Disponible** y conserva el total institucional como contexto secundario.


## Fase 9

**Despacho individual P4 → funcionario — implementada.**

Incluye:
- código automático `DSP-######`;
- selección de P4 activo;
- funcionario receptor consultado desde RRHH2029;
- validación de que el receptor pertenezca a la estructura vigente atendida por el P4;
- funcionario que entrega consultado desde RRHH2029;
- snapshots de cédula, posición, rango, dependencia, dirección y oficina;
- fecha/hora, documento/comprobante y observaciones;
- una o varias variantes por despacho;
- salida transaccional del inventario del P4;
- prevención de doble contabilización mediante `source_key`;
- historial de dotación por funcionario;
- consulta de entregas previas antes de crear un nuevo despacho;
- pendientes nominales provenientes de solicitudes con beneficiario;
- vínculo de la entrega con el renglón de solicitud y la transferencia recibida;
- reserva de stock nominal dentro del P4 para evitar que una entrega manual consuma unidades ya destinadas a beneficiarios;
- comprobante imprimible desde la ficha del despacho;
- estados `draft`, `confirmed` y `cancelled`.

### Regla de stock P4

Para entregas manuales:

```text
Disponible manual P4 = stock físico P4 - pendiente nominal recibido
```

Una entrega nominal consume su propia reserva. Una entrega manual no puede utilizar prendas ya recibidas para un beneficiario específico.

### Trazabilidad de origen

La Fase 9 enlaza:

```text
Funcionario
← Despacho individual
← P4
← Transferencia
← Solicitud
← Variante
```

Cuando varios lotes físicos de una misma variante se mezclan en el Almacén Central, la base actual no permite afirmar cuál lote exacto terminó en una persona sin una asignación explícita de procedencia. SINDESG conserva la trazabilidad logística real disponible y no infiere un lote específico sin evidencia.

## Próximo bloque

**Fase 10 — Cambios, devoluciones, bajas y ajustes.**


## Ajuste Fase 9 — entrega excepcional fuera de adscripción

La validación territorial del P4 se mantiene como regla por defecto, pero ya no bloquea de forma absoluta casos administrativos válidos.

En el formulario de despacho se incorpora la casilla **Habilitar entrega**:

- desmarcada: SINDESG exige que el funcionario pertenezca a la estructura vigente atendida por el P4;
- marcada: permite una entrega excepcional fuera de adscripción;
- el **motivo/autorización es obligatorio** cuando se habilita la excepción;
- la excepción y su motivo quedan persistidos en el despacho y visibles en el comprobante;
- no modifica RRHH2029 ni altera la adscripción del funcionario.

Esto cubre casos como comisión, traslado reciente, autorización superior o desfase temporal entre la realidad operativa y la actualización de RRHH.


## Fase 10

**Cambios, devoluciones, bajas y ajustes — implementada.**

Incluye:
- código automático `AJU-######`;
- operaciones en borrador, confirmadas o canceladas;
- devolución reutilizable desde funcionario al P4;
- devolución no reutilizable con trazabilidad sin reingresar stock;
- cambio de talla/código ligado a una entrega individual;
- reposición ligada a una entrega individual;
- deterioro en almacén;
- baja autorizada;
- pérdida;
- faltante;
- sobrante;
- ajuste autorizado de entrada o salida;
- conteo cíclico con diferencia automática;
- responsable consultado desde RRHH2029;
- documento soporte y motivo;
- stock antes/después persistido al confirmar;
- movimientos compensatorios en kardex con `source_key` idempotente;
- bloqueo de salidas que dejarían stock negativo;
- vínculo directo desde una entrega confirmada hacia "Devolver / cambiar".

### Reglas contables

SINDESG no edita saldos ni borra movimientos anteriores.

```text
Devolución reutilizable: funcionario → P4 = IN
Devolución no reutilizable: trazabilidad, sin reingreso a stock
Cambio reutilizable: IN variante devuelta + OUT variante nueva
Cambio no reutilizable: OUT variante nueva
Reposición: OUT variante de reposición
Deterioro / baja / pérdida / faltante: OUT
Sobrante: IN
Ajuste autorizado: IN u OUT según documento
Conteo: diferencia entre sistema y conteo = IN / OUT / sin movimiento
```

Para devoluciones y cambios, SINDESG controla que no se devuelva una cantidad mayor a la originalmente entregada menos devoluciones/cambios ya confirmados.

## Próximo bloque

**Fase 11 — Reportes y rendición.**


## Fase 11

**Reportes y rendición — implementada.**

Incluye un centro de reportes de solo lectura con filtros combinables y exportación.

### Reportes disponibles

- stock actual por almacén/P4, categoría, artículo, talla y aplicación;
- artículos próximos a agotarse mediante umbral configurable;
- kardex completo;
- entradas por período;
- salidas por período;
- recepciones y lotes;
- solicitudes y pendientes;
- transferencias Central → P4;
- entregas individuales P4 → funcionario;
- dotación por funcionario;
- ajustes, devoluciones, cambios, bajas y conteos.

### Filtros

Según el reporte:
- fecha desde/hasta;
- almacén/P4;
- categoría;
- artículo;
- talla;
- aplicación/sexo del artículo;
- estado;
- dirección IN/OUT;
- funcionario;
- búsqueda por nombre, código, documento o referencia;
- umbral de bajo stock.

### Salidas

- vista web tabular;
- exportación compatible con Excel en formato `.xls` sin dependencia externa;
- vista optimizada para impresión y **Guardar como PDF** desde el navegador.

### Reglas

- los reportes no escriben datos;
- el stock se deriva de `v_inventory_stock` y del kardex;
- los históricos usan snapshots ya persistidos;
- RRHH2029 permanece en solo lectura;
- la vista en pantalla limita resultados para mantener rendimiento y la exportación admite un volumen mayor.

## Próximo bloque

**Fase 12 — Auditoría, seguridad y administración.**
