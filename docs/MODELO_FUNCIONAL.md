# Modelo funcional de SINDESG

## Dashboard principal

Pantalla interactiva de entrada al sistema.

Debe permitir:
- consultar indicadores;
- abrir categorías;
- entrar al detalle de productos;
- navegar a solicitudes, inventario, lotes, distribución y reportes;
- visualizar alertas y movimientos recientes.

## Módulos

### 1. Catálogo de artículos
Gestiona:
- categorías;
- productos;
- variantes;
- tallas;
- marcas;
- imágenes;
- códigos;
- estado.

### 2. Recepción de lotes
Gestiona:
- ingresos;
- documentos;
- proveedor/procedencia;
- desglose por variante;
- responsable;
- entrada a inventario.

### 3. Producción interna
Gestiona artículos confeccionados por Servicios Generales:
- orden de producción;
- cantidades;
- tallas;
- producto terminado;
- responsable;
- insumos si aplica.

### 4. Inventario
Permite:
- consultar stock;
- filtrar por almacén;
- ver tallas;
- ver lotes;
- ver disponibilidad;
- abrir kardex;
- realizar conteos autorizados.

### 5. Solicitudes
Permite a las unidades autorizadas:
- preparar listado;
- seleccionar funcionarios desde RRHH;
- solicitar cantidades;
- enviar;
- seguir estado;
- atender parcial o totalmente.

### 6. Distribución Central → P4
Controla:
- preparación;
- aprobación;
- entrega;
- recepción;
- transferencia de stock;
- pendientes;
- responsables.

### 7. Despacho P4 → funcionario
Controla entrega final:
- funcionario;
- posición;
- rango;
- unidad;
- producto;
- talla;
- cantidad;
- fecha;
- responsable;
- comprobante.

### 8. Funcionarios
Consulta exclusivamente datos autorizados de RRHH y muestra:
- identidad institucional;
- posición;
- rango;
- estado;
- unidad actual;
- historial de dotaciones SINDESG.

### 9. Territorio
Consulta la estructura vigente proveniente de RRHH y permite visualizar la jerarquía institucional.

### 10. Almacenes / P4
Configura qué unidades oficiales operan como almacén:
- Almacén Central;
- P4 Dirección;
- P4 Zona;
- otros autorizados.

### 11. Movimientos / kardex
Registra:
- entrada;
- salida;
- transferencia;
- devolución;
- cambio;
- baja;
- ajuste;
- conteo.

### 12. Cambios y devoluciones
Resuelve:
- cambio de talla;
- sustitución;
- devolución;
- reposición;
- cambio de código.

### 13. Reportes
Incluye:
- inventario actual;
- stock por almacén;
- stock por talla;
- entradas/salidas;
- transferencias;
- entregas por funcionario;
- entregas por unidad;
- solicitudes;
- lotes;
- diferencias;
- artículos con bajo stock.

### 14. Auditoría
Registra acciones críticas y permite investigación posterior.

### 15. Administración
Gestiona:
- usuarios;
- roles;
- permisos;
- parámetros;
- tipos de movimiento;
- reglas de operación.

## Flujos principales

### Flujo A — Compra o recepción externa

```text
Recepción
→ clasificación
→ lote
→ variantes/tallas
→ movimiento de entrada
→ stock Almacén Central
```

### Flujo B — Producción interna

```text
Orden de producción
→ artículo producido
→ clasificación
→ lote interno
→ movimiento de entrada
→ stock Almacén Central
```

### Flujo C — Solicitud de P4

```text
P4
→ selecciona necesidad
→ consulta personal RRHH
→ envía solicitud
→ Servicios Generales revisa
→ aprobación total/parcial
```

### Flujo D — Central a P4

```text
Solicitud aprobada
→ preparación
→ salida Central
→ transferencia
→ recepción P4
→ entrada P4
```

### Flujo E — P4 a funcionario

```text
Funcionario RRHH
→ validar historial
→ seleccionar artículo/talla
→ entrega
→ salida P4
→ historial individual
```

## Reglas transversales

1. No duplicar funcionarios de RRHH.
2. No emparejar unidades por nombre libre.
3. No borrar movimientos consumados.
4. No modificar stock directamente sin movimiento.
5. Toda operación sensible debe identificar actor, fecha y motivo.
6. El historial debe sobrevivir a traslados y cambios de rango.
7. Las fotos de artículos son administradas en SINDESG.
8. Las nuevas unidades territoriales provienen de RRHH.
9. La condición de ser P4 se administra en SINDESG.
10. Las importaciones históricas deben conservar trazabilidad del archivo de origen.
