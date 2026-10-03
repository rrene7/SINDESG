# Hoja de ruta de SINDESG

Esta hoja de ruta organiza el desarrollo por bloques pequeños, verificables y desplegables en XAMPP.

## Fase 0 — Base documental y decisiones de arquitectura

**Objetivo:** dejar documentado qué problema resuelve SINDESG y cómo se desarrollará.

### Alcance
- Definir módulos.
- Definir flujo logístico.
- Documentar integración RRHH2029.
- Definir base de datos propia `sindesg`.
- Definir metodología GitHub → validación → XAMPP local.
- Documentar lineamientos visuales del dashboard.

### Criterio de cierre
- Documentación inicial en `main`.
- Estructura de proyecto acordada.
- Sin alterar RRHH2029.

---

## Fase 1 — Núcleo técnico y entorno local

**Objetivo:** levantar una primera versión navegable en XAMPP.

### Entregables
- Estructura PHP base.
- Configuración de entorno.
- Conexión MariaDB.
- Manejo centralizado de configuración.
- Layout general.
- Dashboard inicial.
- Menú principal.
- Manejo básico de errores.
- Script de instalación de base de datos.
- Datos mínimos de prueba.

### Criterio de cierre
- `http://localhost/SINDESG/` carga correctamente.
- Conexión a la base `sindesg` validada.
- No requiere modificar RRHH2029.

---

## Fase 2 — Catálogo maestro e inventario

**Objetivo:** representar correctamente los artículos existentes y futuros.

### Entregables
- Categorías.
- Productos.
- Variantes.
- Tallas.
- Marcas.
- Fotografías.
- Código institucional/ISTMO cuando exista.
- Código interno SINDESG cuando no exista código externo.
- Estados de artículo.
- Consulta por categoría, talla, código y lote.
- Vista visual del inventario inspirada en el dashboard aprobado.

### Regla de diseño
Una talla o variante no se resuelve creando columnas como `talla_1`, `talla_2` o `codigo_camisa_1`. Las variantes deben ser registros relacionados.

### Criterio de cierre
- Se puede crear un producto con o sin código externo.
- Se puede asociar foto.
- Se puede consultar stock por variante.

---

## Fase 3 — Recepción de lotes

**Objetivo:** controlar toda entrada al Almacén Central.

### Entregables
- Recepciones.
- Lotes.
- Documento soporte.
- Procedencia.
- Proveedor si aplica.
- Fecha de ingreso.
- Desglose por producto/variante/talla.
- Usuario que recibe.
- Estado del lote.
- Entrada automática al kardex.

### Tipos de procedencia iniciales
- Compra.
- Transferencia.
- Donación.
- Devolución.
- Otro autorizado.

La producción interna tiene su propio flujo en la Fase 4 para conservar separadamente la confección, los insumos consumidos y el producto terminado.

### Criterio de cierre
- Una recepción incrementa inventario mediante movimientos, no editando saldos manualmente.

---

## Fase 4 — Producción interna

**Objetivo:** registrar prendas confeccionadas dentro de Servicios Generales.

### Entregables
- Órdenes de producción.
- Producto terminado.
- Cantidad.
- Tallas.
- Insumos consumidos, cuando aplique.
- Responsable.
- Fecha.
- Código interno.
- Entrada al inventario.

### Criterio de cierre
- Un artículo fabricado internamente puede existir y moverse aunque nunca tenga código de proveedor/ISTMO.

---

## Fase 5 — Integración RRHH2029

**Objetivo:** consultar personal y territorio sin duplicar la fuente maestra.

### Fuentes previstas
- funcionarios;
- posición vigente;
- rango;
- estado;
- unidad actual;
- estructura territorial vigente.

### Reglas
- Solo lectura.
- No escribir desde SINDESG hacia RRHH2029.
- Relacionar por IDs técnicos, no por nombres.
- Conservar snapshot histórico de unidad/rango en cada entrega.

### Criterio de cierre
- Buscar un funcionario y obtener automáticamente sus datos vigentes.
- Navegar la estructura territorial vigente.
- Cambios posteriores de RRHH se reflejan en SINDESG sin recrear registros manualmente.

---

## Fase 6 — Almacenes y P4

**Objetivo:** representar la red real de distribución.

### Entregables
- Almacén Central.
- Almacenes/P4 de Direcciones.
- Almacenes/P4 de Zonas.
- Vinculación a `organization_unit_id` de RRHH.
- Responsable.
- Estado activo/inactivo.
- Existencia por almacén.

### Regla
Que una unidad exista en RRHH no significa automáticamente que sea un P4. La habilitación como almacén pertenece a SINDESG.

### Criterio de cierre
- El sistema puede mostrar inventario separado del Central y de cada P4.

---

## Fase 7 — Solicitudes

**Objetivo:** digitalizar la necesidad enviada por Direcciones, Zonas y P4.

### Entregables
- Borrador.
- Envío.
- Revisión.
- Aprobación total/parcial.
- Rechazo.
- Pendientes.
- Detalle por funcionario y/o cantidades según el proceso autorizado.
- Validación de duplicidades.
- Validación de stock.

### Estados iniciales
- borrador;
- enviada;
- en revisión;
- aprobada;
- parcialmente aprobada;
- rechazada;
- atendida;
- cerrada.

---

## Fase 8 — Distribución Almacén Central → P4

**Objetivo:** controlar formalmente las transferencias institucionales.

### Entregables
- Preparación.
- Autorización.
- Entrega.
- Recepción.
- Almacén origen.
- Almacén destino.
- Requisición/documento.
- Responsable que entrega.
- Responsable que recibe.
- Transferencias parciales.
- Pendientes.

### Regla contable
Mover 100 unidades del Central a un P4:
- disminuye Central en 100;
- aumenta P4 en 100;
- no reduce el inventario institucional total.

---

## Fase 9 — Despacho individual P4 → funcionario

**Objetivo:** cerrar la cadena logística hasta la persona.

### Entregables
- Búsqueda desde RRHH.
- Selección de artículo/talla.
- Cantidad.
- Historial del funcionario.
- Validación de entregas previas.
- Usuario que entrega.
- Fecha y hora.
- Comprobante.
- Disminución de inventario del P4.

### Criterio de cierre
Cada unidad entregada puede rastrearse desde su recepción/lote hasta el funcionario final.

---

## Fase 10 — Cambios, devoluciones, bajas y ajustes

**Objetivo:** resolver movimientos posteriores sin borrar historia.

### Movimientos
- Cambio de talla.
- Cambio de código.
- Devolución.
- Reposición.
- Deterioro.
- Baja.
- Pérdida.
- Sobrante.
- Faltante.
- Ajuste autorizado.
- Conteo cíclico.

### Regla
Nunca corregir inventario eliminando el movimiento anterior. Se registra un nuevo movimiento compensatorio y auditable.

---

## Fase 11 — Reportes y rendición

**Objetivo:** sustituir cálculos manuales y evitar duplicidades de los Excel históricos.

### Reportes
- Stock actual.
- Stock por almacén.
- Stock por categoría y talla.
- Kardex.
- Entradas por período.
- Salidas por período.
- Transferencias.
- Entregas individuales.
- Entregas por Dirección/Zona.
- Solicitudes.
- Pendientes.
- Lotes.
- Conteos y diferencias.
- Artículos próximos a agotarse.
- Dotación por funcionario.

### Exportación
- Excel.
- PDF.
- impresión.

---

## Fase 12 — Auditoría, seguridad y administración

**Objetivo:** garantizar trazabilidad institucional.

### Entregables
- Usuarios.
- Roles.
- Permisos.
- Auditoría de acciones.
- Valor anterior/nuevo cuando corresponda.
- Sesión.
- Fecha/hora.
- Módulo.
- Registro afectado.
- Parámetros del sistema.

---

## Fase 13 — Migración controlada de históricos

**Objetivo:** incorporar los Excel existentes sin contaminar la base nueva.

### Trabajo previo
- Deduplicar.
- Normalizar fechas.
- Separar observaciones de fechas.
- Resolver errores de fórmulas.
- Mapear nombres históricos a IDs oficiales.
- Conservar referencia al archivo y fila de origen.
- Importar por lotes auditables.

### Regla
No importar directamente una hoja completa sin validación y conciliación previa.

---

## Fase 14 — Estabilización y puesta en producción

### Entregables
- Pruebas integrales.
- Corrección de incidencias.
- Respaldo.
- Manual técnico.
- Manual de usuario.
- Procedimiento de actualización.
- Plan de recuperación.
- Despliegue institucional.

---

# Orden de desarrollo inmediato

Estado actual del desarrollo por bloques:

- Fases 0–4: implementadas y validadas en XAMPP.
- Fase 5: integración RRHH2029 implementada.
- Siguiente bloque después de validar Fase 5 en XAMPP: **Fase 6 — Almacenes y P4**.

No se iniciará un módulo posterior hasta verificar el bloque anterior en GitHub y en el entorno local.
