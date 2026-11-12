# P4 por cabecera y dependencias RRHH2029

## Regla operativa

Cada P4 tiene una **unidad logística efectiva** en `warehouses.organization_unit_id`.
Esa unidad define el almacén, sus existencias, solicitudes, despachos y permisos
por `warehouse_id`. Las reglas de aislamiento P4 no cambian.

La cabecera `Dirección Nacional / Dirección / Zona Policial / Servicio` se
selecciona primero y queda registrada en `p4_head_unit_id` con snapshots de
nombre, código y tipo. El usuario decide:

1. **P4 de cabecera:** dejar la opción de dependencia sin marcar. La unidad
   efectiva y la cabecera son la misma.
2. **P4 de dependencia:** activar la opción, buscar una unidad descendiente
   (centro, área u oficina, incluso a varios niveles) y seleccionarla. La
   unidad efectiva es la dependencia; conserva su cabecera superior.

Ejemplo conceptual: la Dirección Nacional de Seguridad Penitenciaria puede
tener su P4 central y cada centro penitenciario bajo esa Dirección puede tener
otro P4. Cada P4 administra un inventario independiente con código único.

La pertenencia se determina por la cadena oficial `parent_id` de RRHH2029.
No se acepta una dependencia de otra cabecera, inactiva, sin vigencia o sin
registro. Tampoco se crean dos P4 para la misma unidad, incluso si uno está
inactivo. RRHH2029 solo se consulta (SELECT y WITH RECURSIVE).

## Migración 024, aditiva y manual

Archivo: `database/schema/024_p4_subordinate_warehouses.sql`.

Agrega cuatro campos `p4_head_unit_*` a `sindesg.warehouses` sin borrar
registros, sin cambiar `organization_unit_id`, sin afectar el índice único
existente ni modificar RRHH. Los P4 anteriores conservan estos campos NULL
porque no se conoce con certeza su cabecera histórica.

Antes de publicar el nuevo código, crear y **verificar un respaldo reciente**
de SINDESG (base + uploads); verificar que el servicio no tenga escrituras
activas durante la migración. El instalador solo modifica la base exacta
`sindesg` y exige la ruta de un paquete de respaldo verificado:

```powershell
& 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\SINDESG\scripts\backup.php' '--destination=C:\SINDESG-respaldos' '--label=pre_p4_subordinados'
& 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\SINDESG\scripts\restore.php' '--source=C:\SINDESG-respaldos\<carpeta_generada>' '--verify-only'
& 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\SINDESG\scripts\install_p4_subordinate_warehouses.php' '--apply' '--backup=C:\SINDESG-respaldos\<carpeta_generada>'
& 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\SINDESG\scripts\readiness.php' '--strict'
```

No ejecutar la migración sin respaldo válido ni sustituir `config/database.php`.
Si la validación de producción falla, resolver configuraciones existentes
(por ejemplo, HTTPS y `config/local.php`) antes de autorizar la operación.

## Comprobaciones funcionales

- Crear P4 directamente bajo una cabecera vigente.
- Crear P4 para un centro penitenciario debajo de su Dirección.
- Crear P4 en otra dependencia válida del mismo árbol institucional.
- Comprobar que dos dependencias tienen códigos, inventario y permisos separados.
- Bloquear unidad de otra Dirección, vacante/inactiva o sin vigencia.
- Bloquear P4 duplicado aun cuando el anterior está inactivo.
- Cambiar la cabecera tras seleccionar un centro: la selección secundaria se limpia.
- Verificar que se visualizan cabecera y unidad efectiva en listado y ficha.
