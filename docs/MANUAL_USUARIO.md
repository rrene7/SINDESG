# Manual de usuario de SINDESG

**Sistema de Inventario y Despacho de Servicios Generales**

Versión documental: Fase 14C

## Elegibilidad administrativa para solicitar dotación en P4

En **Solicitudes P4** se admiten funcionarios identificados en RRHH2029
con posición vigente, aunque estén en vacaciones ordinarias, licencia
con sueldo por estudio, otros permisos o condiciones administrativas.
La unidad actual tampoco debe coincidir necesariamente con la del P4:
se admite personal prestado bajo responsabilidad del P4 solicitante.

Para este procedimiento, el indicador interno `statuses.is_active` **no
representa por sí solo el estado administrativo «Inactivo»**. Una licencia
o permiso puede tener `is_active = 0` en RRHH y seguir siendo elegible
para **solicitud nominal P4**. Se evalúa el indicador específico
`is_inactive`, el nombre real del estado y el código institucional
`15` de Vacaciones por jubilación; el estado `blocks_actions` no debe
descartar automáticamente a quien tiene permiso o licencia.

**ÚNICOS estados administrativos excluidos por la regla de dotación P4:**
- **Vacaciones por jubilación** (código de estado RRHH2029 15).
- **Inactivo** / condición de inactividad del expediente RRHH.

La solicitud por Excel y la selección nominal manual aplican la misma
regla. Los estados admitidos se muestran como información al P4;
los excluidos se indican como incidencia con el estado concreto, sin
cambiar información en RRHH2029. Se sigue exigiendo identidad verificable,
posición actual inequívoca, variante/talla válida y ausencia de
conflictos o duplicados incompatibles dentro de la solicitud.

**La elegibilidad para nominar NO equivale a autorización de entrega
física.** Las validaciones de funcionarios solicitantes/revisores,
despachos, firmas y entregas individuales mantienen sus reglas
específicas; tampoco se crean existencias o movimientos por importar.

## Filas repetidas y coincidencias de nombre en solicitudes P4

La prevalidación nominal cuenta por separado **filas del Excel**,
**funcionarios únicos verificables**, **repetidos omitibles** y
**registros bloqueados**. Una fila repetida no es una segunda dotación.

Si varias filas del Excel comparten una posición RRHH vigente y
única, el mismo artículo y la misma talla, y los nombres de todas
ellas son compatibles con el expediente, se conserva **una sola
asignación** en el borrador. El resto se informa como
«Repetido: se omite», identificando la primera fila. Nunca se
crean dos asignaciones para el mismo funcionario por ese motivo.
La numeración repetida en la columna N° no basta para declarar
dos funcionarios duplicados; se usa la **posición** y se valida
también la identidad.

Cuando una misma posición aparece con **tallas diferentes**, un
nombre que no corresponde, posición no vigente/inactiva o identidad
ambigua, se **bloquean todas las filas contradictorias**.
No se elige al azar qué talla corresponde. El P4 debe aclararlas
antes de importar el documento completo.

Se aceptan ciertas diferencias menores de una letra en nombres o
apellidos de longitud suficiente, exclusivamente después de
resolver una posición RRHH vigente y única y manteniendo una
coincidencia clara del nombre/apellido. Esto no convierte una
**licencia con sueldo por estudio**, una baja o una **posición
anterior** en estado automáticamente elegible: esos casos
continúan pendientes de revisión conforme al estado RRHH.

La cantidad declarada del documento (por ejemplo, 723) se mantiene
intacta y el sistema informa las filas omitidas sin modificar el
Excel original. Si hay bloqueos, la carga íntegra no se guarda;
la importación parcial continúa exigiendo autorización expresa.
No se generan existencias ni movimientos.

## Conciliación de últimos errores nominales

La vista previa de solicitudes P4 muestra diferencias **documentales**
cuando el encabezado declara una cantidad distinta de las filas
nominales realmente identificadas. Una requisición que declara 723
pares y solo tiene 722 filas **no puede importarse íntegramente**
sin corregir la fila faltante o el encabezado. La importación
parcial requiere la autorización expresa ya existente.

Los listados que contienen una talla `8 5` dentro de una sola
celda se normalizan a `8.5`, siempre que la variante exista y esté
activa. Se deja una **observación de formato** con la fila original;
no se crean tallas ni unidades adicionales.

Después de verificar posición vigente y única en RRHH2029, se permite
reconocer ciertas pequeñas variantes ortográficas de nombre y
apellido. En nombres de dos palabras, SINDESG admite también el
intercambio de dos letras del primer nombre (por ejemplo,
`AZALE/AZAEL`, `JHONS/JOHNS`, `ELMER/ERMEL`) **únicamente**
si el apellido es exacto y la posición actual RRHH es inequívoca. Esto **no autoriza** asociar registros cuyos nombres
pertenecen claramente a personas distintas. En esos casos, el P4
debe comprobar con RRHH si cambió el ocupante de la posición y
corregir el origen del listado antes de continuar.

Si la requisición declara 723 unidades y el Excel solo lista 722
funcionarios, compare con el documento de origen antes de cambiar la
cantidad. Si se copió una tabla con filtros activos en Excel,
asegúrese de que se hayan incluido todas las filas previstas.
Nunca invente una línea nominal ni reasigne una posición para
completar el total.

La prevalidación nunca escribe datos. No se modifican RRHH2029,
existencias, movimientos ni históricos.

## Cargar al sistema después de Prevalidar: sin volver a adjuntar el Excel

Al pulsar **«Prevalidar y ver incidencias sin guardar»**, SINDESG
conserva una copia temporal **privada fuera de htdocs** del archivo
original durante **una hora** y verifica la huella **SHA-256**.
El informe de conciliación muestra **«Cargar al sistema»**.

El P4 puede marcar y aprobar las discrepancias nominales mediante
**«Validar seleccionados»** y luego pulsar **«Cargar al sistema»**
**sin volver a escoger el archivo Excel**. El botón reutiliza la copia
temporal verificada, pero en el momento de importar **vuelve a
leer el Excel y consultar RRHH2029**, y reconoce únicamente
decisiones registradas y todavía válidas, ligadas a la requisición,
producto, archivo, fila, documento y posición actual.

El archivo privado se elimina después de importarse correctamente,
y una sesión vencida, otra cuenta de usuario, una copia alterada
o un archivo distinto no permiten la carga. Ante un error de
validación el P4 puede corregir las incidencias y reintentar
durante la hora vigente, sin que se importe parcialmente por
accidente. Si queda alguna incidencia, la carga íntegra mantiene
el bloqueo salvo autorización explícita para importar solo los
verificados. La autorización del exceso documental (por ejemplo,
723 declaradas y 722 nominales) sigue siendo independiente y
no inventa funcionarios o existencias.

**Una única excepción después de actualizar el programa:** si
el archivo fue prevalidado **antes** de instalar esta versión,
no existe su copia temporal privada. Debe volver a adjuntarse
**una última vez** para activar el nuevo flujo sin re-subidas.

## Conciliación rápida P4: «Validar seleccionados»

En **Prevalidar y ver incidencias sin guardar**, las diferencias de
nombres que ya tengan un funcionario con posición vigente inequívoca
en RRHH2029 muestran una **casilla Seleccionar** en la última columna.
La barra superior ofrece **Seleccionar todas las incidencias**,
un contador y un único botón **Validar seleccionados**.

El P4 revisa las identidades mostradas en Excel y RRHH, marca únicamente
las que puede confirmar (o usa Seleccionar todas después de revisar la
lista) y pulsa **Validar seleccionados**. Esa pulsación expresa la
responsabilidad del usuario institucional, sin escribir cédulas,
formularios de justificación ni verificaciones por persona.

SINDESG registra automáticamente, para **cada fila marcada**, la
requisición, SHA-256 exacto del Excel, funcionario institucional,
cédula RRHH, posición, usuario responsable, fecha y constancia estándar
de validación. El servidor **vuelve a consultar RRHH2029 mediante
SELECT** y revisa identidad, posición actual, estado permitido,
variante/talla y duplicados. Si alguna selección no cumple, el
grupo completo se rechaza sin guardar un subconjunto. Las decisiones
se almacenan en una transacción de la base `sindesg` y no generan
movimientos, reservas ni entregas.

**Importante:** una posición no equivale a la identidad de su ocupante.
Si Excel nombra a Carlos Lara y RRHH muestra a Omar Barrera, el P4
**no debe seleccionar esa fila** a menos que haya comprobado
documentalmente que el destinatario de la requisición es efectivamente
el expediente RRHH mostrado. Si realmente corresponden a dos
funcionarios distintos, debe corregir el Excel. Nunca se reemplaza
silenciosamente el beneficiario ni se altera RRHH2029.

Las posiciones anteriores, ambiguas, inactivos, vacaciones por
jubilación, tallas inexistentes y duplicados incompatibles no
ofrecen esta selección. La prevalidación inicial sigue siendo solo
lectura y la lista temporal se conserva durante una hora. La
confirmación colectiva sí almacena evidencias en `sindesg`.

Al importar otra vez **exactamente el mismo archivo**, cada evidencia
solo se aplica a esa requisición, producto y fila si siguen iguales
la identidad y posición RRHH. Si el Excel o RRHH cambia, debe
conciliarse nuevamente.

La cantidad documental declarada y el número de filas siguen siendo
**dos datos independientes**. Si la requisición declara **723** y
contiene **722 funcionarios**, el P4 puede importar **todos los 722
beneficiarios del Excel** después de resolver las incidencias y marcar
la casilla **«Autorizo registrar diferencia documental pendiente»**.
SINDESG deja constancia en **auditoría institucional**, con el usuario,
la requisición, el SHA-256 del archivo, los totales y la diferencia
de una unidad. Esa unidad **no se convierte** en una dotación, activo,
existencia ni movimiento de almacén. Queda pendiente de aclarar por
los responsables. La solicitud nominal al P4 solo contiene las
personas realmente identificadas.

La autorización de diferencia **no reemplaza** la comprobación de
nombres/identidades: si quedan filas pendientes se sigue exigiendo
resolución individual o autorización expresa de importación parcial.
Si el listado contiene más personas que unidades declaradas, deben
corregirse los datos del documento antes de una carga íntegra.
Nunca se crea una persona ficticia ni se descuenta inventario durante
esta importación.

### Instalación en XAMPP

Esta funcionalidad requiere la migración **019**:
`database/schema/019_p4_nominal_validation.sql`. Primero cree un
respaldo verificado de SINDESG y luego ejecute
`php scripts/apply_p4_nominal_validation.php` (solo escribe en la
base de datos `sindesg`). No ejecute esta migración sobre RRHH2029.

## Revisar coincidencias RRHH al importar 723 asignaciones nominales

La opción **Solicitudes → Borrador P4 → Importar listado nominal por Excel**
incluye el botón **Prevalidar y ver incidencias sin guardar**.

La prevalidación no crea renglones de solicitud ni cambia inventario.
Muestra el total de funcionarios, cuántos son verificables y cuántos
requieren revisión; además, presenta por fila:
posición escrita en Excel, nombre Excel, nombre del funcionario resuelto
en RRHH2029, talla y razón de la observación.

El reconocimiento se hace primero por **posición actual exacta**.
Una posición ambigua que pertenezca a dos funcionarios distintos,
inexistente o inactiva NO se vincula automáticamente por similitud de
nombres. Varias filas técnicas del mismo funcionario/posición en RRHH
no se consideran funcionarios diferentes.

La comparación del nombre contempla nombres y apellidos distribuidos
en distintos campos, tildes y componentes adicionales. Exige al menos
dos coincidencias de palabras válidas: si los nombres siguen sin
coincidir suficientemente, la fila continúa pendiente de revisión.

La diferencia de total entre encabezado y listado genera una
**advertencia documental** que debe revisarse, pero no atribuye una
identidad distinta a un funcionario verificado. En el archivo original
de Colón hubo una diferencia 688 vs. 723; no se asume que un encabezado
equivocado cambie las asignaciones nominales verificadas.

La importación por defecto **no guarda nada** si hay filas bloqueadas.
Para avanzar con un subconjunto hay que volver a seleccionar el archivo
y autorizar explícitamente la importación parcial; las filas no
verificadas permanecen **sin cargar**, no se asignan a otro funcionario.
Las decisiones corresponden al P4 una vez conciliado RRHH.

Si una búsqueda manual por un número Excel presenta un expediente con
**otra posición actual** en RRHH, no modifique automáticamente la
posición del Excel ni fuerce una asociación. Revise el expediente y
solicite la referencia actual correcta a la unidad de RRHH.

No se modifica la base RRHH2029, el kardex, los históricos ni las
existencias operativas.

## P4: búsqueda institucional y fecha de última dotación

En **Solicitudes → Borrador P4 → Asignar dotación a varios funcionarios**
la búsqueda de RRHH2029 es **general**. Puede seleccionar funcionarios
adscritos a otra dirección o zona si el P4 solicitante es responsable
de su dotación (por ejemplo, personal prestado). Se conserva la unidad
actual que muestra RRHH2029 sin modificarla.

Después de seleccionar una variante, el buscador consulta **la última
entrega individual confirmada del mismo artículo**, con cualquier talla
y por cualquier P4, y muestra solo la **fecha**. Cuando no hay entrega
del artículo confirmada en SINDESG, indica **«Sin entrega registrada de
este artículo en SINDESG»**, lo que NO significa que no exista una
dotación previa en documentos históricos o fuera del sistema. Si no
responde la consulta, se informa «historial no disponible» en lugar
de reportar falsamente ausencia de entrega.

La fecha es **informativa**; el P4 decide si incorpora al funcionario.
La entrega previa **no bloquea** la selección. Sí se conserva el control
contra registrar dos veces el mismo funcionario y producto dentro
de una **misma solicitud**, y la validación de RRHH y variantes activas.
La solicitud no acredita entrega física ni crea existencias.

En **Importar listado nominal por Excel** se admite personal de otras
zonas y se valida principalmente su posición; nombre y talla se
concilián con RRHH y el catálogo. Al importar, las filas del borrador
muestran la última entrega del mismo artículo confirmada en SINDESG.
La persona responsable del P4 podrá revisar esas fechas y retirar
cualquier asignación antes de enviar la solicitud.

Se incluye **Descargar plantilla Excel (.xlsx)** para crear listados
nominales. Excel abre cada dato en su propia columna, sin depender del
separador regional de CSV. Complete el número de requisición en **B2**,
el contrato en **B3** (si aplica), el total declarado en **B4** y
el P4 responsable en **B5**. Desde la fila **8**, agregue un funcionario
por renglón: rango, posición, nombre, apellido y talla. La columna
**N° es opcional** y las filas vacías no generan dotaciones.
Puede conservar el archivo en formato .xlsx; no necesita convertirlo a CSV.
Se admiten hasta 1,000 funcionarios en una carga nominal.

El módulo de **Entrega individual** conserva su validación y registro
de excepciones por adscripción; esta mejora solo flexibiliza la fase
de solicitud. El saldo inicial y el kardex no se alteran.

## Preparar calzado institucional sin inventario inicial

La clasificación confirmada para el piloto P4 es:

- **CALZADO → BOTAS TÁCTICAS** (uso habitual)
- **CALZADO → ZAPATOS DE CHAROL**
- **CALZADO → ZAPATOS REGULARES**

Desde **Inventario → Preparar catálogo de calzado**, un usuario con
permiso de administración de inventario puede marcar la confirmación
y preparar los tres artículos en **una sola operación**. Si ya existe
un artículo con ese nombre, se reutiliza incluso si estaba bajo la
categoría histórica «Botas». Si existen dos artículos homónimos
ambiguos o inactivos, la operación se bloquea para revisión en vez
de crear duplicados. No se cambian datos existentes.

Los nuevos artículos nacen **sin variantes ni unidades ficticias**.
En el detalle del artículo use **Agregar varias tallas** e introduzca
únicamente las tallas verificadas separadas por espacios o punto
y coma. Por ejemplo: `8 8.5 9 9.5 10`. Las medias tallas usan
punto; se rechaza `8,5` ambiguo. Un lote puede contener hasta 40
tallas y se ejecuta de forma atómica. Repetir una talla ya
registrada no crea un duplicado. Si existen tallas duplicadas o
inactivas se exige resolverlas antes de continuar.

**No cargar cifras estimadas como stock.** Los nombres se consideran
un catálogo provisional hasta cotejar el modelo oficial y los
códigos ISTMO. Los códigos internos SINDESG se generan automáticamente,
pero los códigos institucionales o del proveedor permanecen vacíos
si todavía no fueron verificados.

Después de crear las variantes por talla, vuelva a la solicitud
en borrador del P4 de Colón: podrá buscar `botas tácticas` en
el selector y elegir el producto para importar el listado nominal.
Solo en esa etapa se registran las necesidades individuales; la
aprobación y el despacho exigen stock real disponible.

## Buscar artículos, tallas y botas en solicitudes P4

En **Solicitudes → Nueva solicitud → abrir borrador**, los campos
**Artículo / variante**, **Artículo y talla para varios funcionarios**
y **Producto del catálogo para importar Excel** incluyen ahora
un buscador por coincidencias. Puede escribir varias palabras
en cualquier orden, por ejemplo «botas 8.5», «calzado 9»,
un nombre de artículo, categoría, marca o código institucional.

La búsqueda no exige stock: también se muestran variantes activas con
existencia **0**, porque una solicitud en borrador no mueve bienes.

Si no aparecen botas, vaya a **Inventario → Buscar «bota»** y revise si
el producto está registrado. Si no existe, use **Nueva categoría**
(en caso de no existir CALZADO) y **Nuevo artículo** para registrar el
tipo de botas realmente requerido, luego agregue sus variantes
por talla desde el detalle del artículo. El producto y sus
variantes deben quedar activos. No cambie nombres de otros artículos
para hacerlos parecer botas y no registre saldos inventados.
Si un modelo de botas es diferente, debe tener su propio
artículo y variantes identificables.

**Importante:** el catálogo histórico 2026 importado contiene uniformes
y otras prendas, pero no incluye automáticamente el artículo botas.
Registrar artículos o tallas **no aumenta existencias**.
Las 723 asignaciones nominales de Colón son una prueba del proceso;
no pueden aprobarse ni despacharse sin stock operativo documentado.

## 1. Objetivo

SINDESG permite controlar el catálogo, las existencias, recepciones, producción interna, solicitudes, transferencias a P4, entregas a funcionarios, ajustes, reportes, auditoría y administración de usuarios.

Este manual describe el uso operativo. Las opciones visibles dependen del rol y de los permisos asignados a cada usuario.

## 2. Ingreso al sistema

Abra la dirección institucional de SINDESG y escriba su usuario y contraseña.

Si la sesión permanece inactiva por el tiempo configurado, el sistema puede solicitar autenticación nuevamente.

No comparta credenciales ni utilice cuentas de otra persona.

## 3. Navegación

El menú principal incluye accesos directos a:

- Dashboard;
- Inventario;
- Recepción;
- Solicitudes;
- Distribución;
- Reportes.

En **Más** pueden aparecer, según permisos:

- Producción interna;
- Despacho individual;
- Ajustes y devoluciones;
- Almacenes y P4;
- Funcionarios;
- Migración histórica;
- Auditoría;
- Administración.

El buscador superior permite localizar módulos y categorías.

## 4. Dashboard

El Dashboard presenta un resumen operativo y accesos rápidos. Los datos proceden de SINDESG y pueden incluir categorías, existencias, distribución por almacén y recepciones recientes.

Use el Dashboard para orientarse; las operaciones se realizan en el módulo correspondiente.

## 5. Inventario

El módulo **Inventario** permite consultar categorías, productos, variantes, tallas, códigos, imágenes y existencias.

Los usuarios autorizados pueden:

- crear categorías;
- crear productos;
- agregar variantes;
- asociar imágenes;
- mantener códigos institucionales e internos.

Regla importante: **no corrija una existencia cambiando manualmente el saldo**. Las diferencias deben resolverse mediante recepción, despacho, devolución o ajuste autorizado.

## 6. Recepción de lotes

Use **Recepción** cuando ingrese mercancía por compra, transferencia, donación, devolución u otra procedencia autorizada.

Flujo general:

1. abrir Recepción;
2. crear una nueva recepción;
3. completar documento/procedencia y datos requeridos;
4. agregar artículos y cantidades;
5. guardar borrador si aún falta información;
6. revisar el detalle;
7. confirmar la recepción.

Al confirmar, SINDESG registra los movimientos de entrada correspondientes. No confirme un documento si cantidades, variantes o procedencia no han sido verificadas.

## 7. Producción interna

Use **Producción interna** para artículos confeccionados o producidos dentro de Servicios Generales.

Flujo general:

1. crear orden;
2. seleccionar producto/variantes;
3. registrar cantidades;
4. guardar borrador;
5. revisar;
6. confirmar.

La confirmación genera la entrada correspondiente al inventario.

## 8. Funcionarios

El módulo **Funcionarios** consulta la información vigente desde RRHH2029.

Puede utilizarse para localizar personal, consultar su adscripción vigente y acceder al historial logístico disponible en SINDESG.

Los datos de RRHH no se editan desde SINDESG. Si un rango, posición o unidad está incorrecto, la corrección debe realizarse en la fuente oficial de RRHH.

## 9. Almacenes y P4

Este módulo configura qué unidades autorizadas operan como almacenes.

Puede incluir:

- Almacén Central;
- P4 de Dirección;
- P4 de Zona;
- otro almacén autorizado.

Una unidad existente en RRHH no se convierte automáticamente en P4. La habilitación logística se administra dentro de SINDESG.

## 10. Solicitudes

Flujo general:

1. crear solicitud;
2. agregar artículos/necesidades;
3. revisar el borrador;
4. enviar;
5. iniciar revisión;
6. aprobar total/parcialmente o rechazar según corresponda.

Una solicitud no mueve inventario por sí sola. El movimiento ocurre en los procesos logísticos posteriores.

## 11. Distribución Central → P4

Use **Distribución** para transferir inventario del Almacén Central hacia un P4.

Estados operativos disponibles en el flujo incluyen preparación, autorización, despacho y recepción.

Flujo recomendado:

1. crear/preparar la transferencia;
2. verificar origen, destino y cantidades;
3. autorizar;
4. despachar desde el origen;
5. registrar la recepción en el destino.

Una transferencia mueve stock entre almacenes; no representa consumo final.

## 12. Despacho individual

Use **Despacho individual** para la entrega final a un funcionario.

Flujo general:

1. seleccionar al funcionario desde RRHH;
2. verificar identidad, rango y unidad;
3. agregar artículos y tallas;
4. revisar cantidades;
5. confirmar la entrega.

SINDESG conserva un snapshot de los datos relevantes del funcionario para que el historial no cambie si después es trasladado o cambia de rango.

Antes de confirmar, revise cuidadosamente funcionario, P4, variante, talla y cantidad.

## 13. Ajustes y devoluciones

Este módulo resuelve situaciones posteriores sin borrar historia:

- devolución;
- cambio;
- baja;
- pérdida;
- sobrante;
- faltante;
- ajuste autorizado;
- conteo.

Regla principal: **no se elimina el movimiento anterior para corregirlo**. Se registra un movimiento compensatorio y auditable.

Flujo general:

1. crear el ajuste;
2. seleccionar tipo y motivo;
3. indicar artículo/almacén/cantidad;
4. revisar;
5. confirmar.

## 14. Reportes

El módulo **Reportes** permite consultar información operativa y gerencial y dispone de salidas para impresión y Excel cuando corresponde.

Utilice filtros antes de exportar para reducir el reporte al período, almacén, artículo o criterio requerido.

Los reportes consultan la información registrada; no sustituyen la revisión del kardex cuando se investiga una diferencia específica.

## 15. Migración histórica

El histórico 2026 fue procesado mediante una migración controlada. En lotes cerrados verá:

- snapshots archivados del Control de inventario;
- referencias históricas de ENTRADAS/SALIDAS/rendiciones;
- estado **Referencia** para filas conservadas que no forman parte del stock vivo.

`Referencia` significa: conservada para consulta, excluida del stock y del kardex operativo.

No utilice los controles de migración histórica para registrar movimientos actuales.

## 16. Auditoría

Los usuarios autorizados pueden consultar la auditoría por fecha, usuario, módulo, acción o búsqueda.

La auditoría sirve para rastrear operaciones sensibles y cambios administrativos. No debe editarse como mecanismo de corrección.

## 17. Administración

Disponible solo para usuarios con permiso administrativo.

Permite:

- crear usuarios vinculados a funcionarios vigentes de RRHH;
- activar/desactivar usuarios;
- asignar roles;
- restablecer contraseñas;
- administrar permisos de roles;
- definir parámetros de seguridad.

Protecciones importantes:

- un administrador no puede desactivar su propia cuenta;
- un administrador no puede retirarse a sí mismo el rol Administrador desde esa operación;
- las contraseñas deben cumplir la longitud mínima configurada.

## 18. Roles

### Administrador SINDESG

Control total de la aplicación, usuarios, roles, permisos y auditoría.

### Almacén Central

Opera catálogo, recepciones, producción, almacenes, solicitudes, distribución, despachos, ajustes y reportes según sus permisos.

### P4

Opera los procesos asignados a P4, principalmente solicitudes, distribución/recepción, despacho individual, ajustes y consultas.

### Auditor

Consulta inventario, operaciones, reportes y auditoría sin administrar la operación.

### Consulta

Acceso de lectura a los módulos autorizados.

## 19. Mensajes y errores

Si aparece un mensaje de error:

1. no repita muchas veces la misma operación;
2. verifique los datos mostrados;
3. tome nota del módulo y acción;
4. informe al soporte técnico;
5. no intente corregir directamente la base de datos.

Si RRHH no está disponible, los módulos dependientes de personal pueden quedar temporalmente sin información. No cree personas manualmente para reemplazar RRHH.

## 20. Buenas prácticas

- revisar antes de confirmar;
- utilizar el funcionario correcto;
- seleccionar la variante/talla correcta;
- no compartir cuentas;
- no alterar saldos manualmente;
- usar ajustes para correcciones;
- registrar motivos claros;
- cerrar sesión al terminar;
- reportar cualquier comportamiento inesperado.

## 21. Qué no debe hacerse

- borrar movimientos para cuadrar inventario;
- escribir datos directamente en RRHH2029;
- usar migración histórica como recepción actual;
- registrar un funcionario duplicado;
- compartir contraseñas;
- restaurar respaldos sin autorización técnica;
- ejecutar scripts SQL fuera del procedimiento aprobado.

## 22. Soporte

Al reportar una incidencia indique:

- módulo;
- acción que intentaba realizar;
- fecha/hora aproximada;
- mensaje mostrado;
- código/producto/funcionario afectado cuando aplique.

No envíe contraseñas ni archivos de configuración con credenciales.
