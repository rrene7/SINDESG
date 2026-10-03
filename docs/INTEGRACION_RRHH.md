# Integración SINDESG ↔ RRHH2029

## Objetivo

Permitir que SINDESG utilice personal y estructura territorial oficial sin mantener una base paralela de Recursos Humanos.

## Modo de acceso

**Solo lectura.**

SINDESG:
- puede consultar;
- puede sincronizar una proyección mínima;
- puede guardar referencias y snapshots históricos.

SINDESG no:
- crea funcionarios en RRHH;
- modifica rangos;
- cambia adscripciones;
- crea territorio en RRHH;
- ejecuta procedimientos de modificación de estructura.

## Fuentes maestras confirmadas

RRHH2029 dispone de objetos para:
- `employees`: maestro de personas;
- `institution_positions`: posiciones;
- `employee_position_assignments`: asignación e historial de posición;
- `organization_units_current`: estructura territorial/institucional canónica;
- `employee_unit_assignments`: relación persona–unidad;
- `ranks`: rangos;
- `statuses`: estados;
- `organization_unit_types`: tipos de unidad.

## Identificadores

Los vínculos se harán mediante:
- `employee_id`;
- `position_number`;
- `organization_unit_id`;
- `system_code`.

No se debe relacionar mediante:
- nombre completo;
- nombre libre de dependencia;
- abreviaturas no únicas.

## Datos mínimos de personal para SINDESG

La proyección autorizada debe incluir, como mínimo:

```text
employee_id
position_number
document_number
first_name
last_name
sex
rank_id
rank_name
status_id
status_name
status_is_active
status_blocks_actions
organization_unit_id
organization_unit_code
organization_unit_name
unit_type
hierarchy_path
unit_assignment_start_date
```

SINDESG no necesita para su operación:
- salario;
- cuentas bancarias;
- familiares;
- salud;
- fotografías de expediente;
- sanciones;
- contactos personales;
- expedientes completos.

## Territorio

SINDESG debe consumir únicamente la estructura canónica vigente.

Campos mínimos:

```text
id
parent_id
unit_type_id
system_code
institutional_code
official_name
official_abbreviation
unit_type
hierarchy_path
lifecycle_status
replacement_unit_id
is_active
```

## Actualización

Se contemplan dos etapas:

### Etapa inicial
Consulta directa de vistas controladas dentro de la misma instancia MariaDB.

### Etapa posterior
API o sincronización incremental basada en cambios.

Orden de sincronización:
1. catálogos;
2. territorio;
3. personas;
4. posiciones/asignaciones.

## Nuevo funcionario

Cuando RRHH crea un funcionario y le asigna posición/unidad:
- RRHH conserva la autoridad del dato;
- SINDESG lo ve en la siguiente consulta/sincronización;
- no es necesario registrarlo manualmente en SINDESG.

## Nuevo territorio

Cuando RRHH agrega una nueva unidad:
- aparece en la proyección territorial;
- SINDESG puede usarla en búsquedas y relaciones;
- no se convierte automáticamente en P4.

## Traslado

Si RRHH traslada a una persona:

```text
Antes:
employee_id 123 → Telemática

Después:
employee_id 123 → otra unidad
```

SINDESG muestra la nueva adscripción como dato actual.

Las entregas históricas conservan el snapshot de la adscripción existente al momento de cada movimiento.

## P4

La figura logística se administra en SINDESG.

Ejemplo conceptual:

```text
warehouse
id: 12
organization_unit_id: 182
code: P4-DNT
name: P4 Telemática
type: P4_DIRECCION
active: 1
```

De esta forma:
- RRHH determina qué es Telemática;
- SINDESG determina si Telemática dispone de un almacén/P4.

## Reglas de elegibilidad

El hecho de que una persona exista no implica que pueda recibir una dotación en cualquier estado.

La capa de integración debe exponer el estado laboral y las banderas necesarias para que SINDESG aplique reglas explícitas.

No se filtrará silenciosamente solo por texto como `ACTIVO`.

## Seguridad

La conexión utilizada por SINDESG para consultar RRHH debe disponer únicamente de los permisos indispensables de lectura sobre las vistas/objetos autorizados.

No guardar credenciales reales dentro del repositorio.

## Historial

Cada entrega individual deberá conservar:

```text
employee_id
position_number_snapshot
document_number_snapshot
employee_name_snapshot
rank_snapshot
organization_unit_id_snapshot
organization_unit_name_snapshot
delivery_date
```

La referencia técnica permite consultar el dato actual; el snapshot conserva la verdad histórica.
