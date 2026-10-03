# Arquitectura inicial de SINDESG

## 1. Principio general

SINDESG se desarrollará como una aplicación web PHP ejecutada en Apache/XAMPP y conectada a MariaDB.

La primera etapa prioriza una arquitectura simple, mantenible y fácil de desplegar en `C:\xampp\htdocs\SINDESG`.

## 2. Separación de responsabilidades

### RRHH2029
Es la fuente maestra de:
- funcionarios;
- cédula/documento institucional;
- posición;
- rango;
- estado laboral;
- adscripción actual;
- estructura territorial/institucional.

### SINDESG
Es la fuente maestra de:
- catálogo de artículos;
- fotos;
- categorías;
- variantes y tallas;
- lotes;
- producción interna;
- almacenes;
- P4;
- inventario;
- solicitudes;
- transferencias;
- despachos;
- devoluciones;
- bajas;
- ajustes;
- auditoría logística.

## 3. Bases de datos

Se utilizará la misma instancia MariaDB de XAMPP, pero con separación lógica:

```text
MariaDB
├── rrhh2029      ← fuente maestra RRHH, SOLO LECTURA desde SINDESG
└── sindesg       ← base propia de la aplicación
```

SINDESG no debe crear, editar ni eliminar registros directamente dentro de `rrhh2029`.

## 4. Capas iniciales de la aplicación

```text
Navegador
   │
   ▼
Apache / XAMPP
   │
   ▼
PHP SINDESG
   ├── Presentación
   ├── Controladores/acciones
   ├── Servicios de negocio
   ├── Acceso a datos SINDESG
   └── Adaptador RRHH solo lectura
          │
          ├── DB sindesg
          └── DB rrhh2029
```

Aunque se utilice PHP sin un framework pesado en la etapa inicial, el código debe mantenerse separado por responsabilidad para evitar páginas monolíticas.

## 5. Estructura de carpetas propuesta

```text
SINDESG/
├── public/
│   ├── index.php
│   └── assets/
│       ├── css/
│       ├── js/
│       └── img/
├── app/
│   ├── Controllers/
│   ├── Services/
│   ├── Repositories/
│   ├── Models/
│   ├── Views/
│   └── Support/
├── config/
│   ├── app.php
│   └── database.example.php
├── database/
│   ├── schema/
│   ├── migrations/
│   ├── seeds/
│   └── imports/
├── storage/
│   ├── logs/
│   └── uploads/
├── tests/
├── docs/
├── scripts/
├── .gitignore
└── README.md
```

La configuración local con credenciales no se versionará.

## 6. Modelo de inventario

El inventario se manejará mediante movimientos.

### Entidades principales previstas

```text
categories
products
product_variants
sizes
brands
product_images

receipts
receipt_items
lots

warehouses
warehouse_stocks (proyección/cache si se requiere)
inventory_movements

requests
request_items

transfers
transfer_items

employee_deliveries
employee_delivery_items

returns
adjustments
cycle_counts

users
roles
permissions
audit_logs
```

El esquema definitivo se construirá por fases y no se crearán todas las tablas de una sola vez.

## 7. Producto, variante y lote

Se separan tres conceptos:

### Producto
Describe qué es:
- Camisa de faena azul.
- Pantalón de faena azul.
- Bota.
- Gorra.
- Kepi.

### Variante
Describe una combinación controlada:
- talla;
- sexo cuando aplique;
- marca;
- color;
- modelo u otra característica.

### Lote
Describe de dónde y cuándo ingresó una cantidad concreta:
- recepción;
- fecha;
- proveedor/procedencia;
- documento;
- producción interna;
- cantidad.

Un producto puede tener muchas variantes y una variante puede ingresar en muchos lotes.

## 8. Códigos

Cada variante debe poder mantener:
- código institucional/ISTMO, si existe;
- código externo/proveedor, si existe;
- código interno SINDESG, obligatorio.

La ausencia de código externo no impide registrar ni despachar un artículo.

## 9. Almacenes y P4

Los almacenes son entidades de SINDESG.

Cada P4 puede vincularse a una unidad oficial de RRHH:

```text
warehouse.organization_unit_id → RRHH organization_units_current.id
```

No toda unidad RRHH es un P4.

Tipos iniciales:
- CENTRAL;
- P4_DIRECCION;
- P4_ZONA;
- OTRO_AUTORIZADO.

## 10. Flujo de inventario

```text
Compra / Producción / Donación / Devolución
                    │
                    ▼
             Recepción / Lote
                    │
                    ▼
             Almacén Central
                    │
             Transferencia
                    ▼
                  P4
                    │
          Despacho individual
                    ▼
               Funcionario
```

La transferencia Central → P4 mueve inventario entre ubicaciones, pero no reduce el inventario institucional total.

## 11. Snapshot histórico

Cuando se realiza un despacho individual, SINDESG debe guardar:
- `employee_id`;
- posición;
- rango en ese momento;
- unidad en ese momento;
- nombre visible en ese momento;
- fecha/hora;
- usuario que entrega.

Así, un traslado futuro en RRHH no cambia la historia de una entrega pasada.

## 12. Auditoría

Las acciones sensibles deberán registrar:
- usuario;
- fecha/hora;
- acción;
- módulo;
- entidad afectada;
- ID afectado;
- valores relevantes antes/después;
- motivo cuando corresponda.

No se borrarán movimientos logísticos consumados para “corregir” inventario. Se realizarán movimientos de reversión/ajuste autorizados.

## 13. Dashboard

El dashboard principal será interactivo y tendrá dos funciones:

1. Resumen operacional.
2. Navegación hacia módulos.

Elementos previstos:
- categorías con foto real;
- stock;
- tallas;
- lotes recientes;
- solicitudes pendientes;
- alertas de reabastecimiento;
- movimientos recientes;
- accesos rápidos.

La estética seguirá la referencia aprobada: moderna, tecnológica, institucional y policial, evitando un diseño administrativo genérico.

## 14. Evolución

La arquitectura debe permitir que SINDESG comience con uniformes y equipos de Servicios Generales, pero sin acoplar el modelo exclusivamente a una prenda determinada.
