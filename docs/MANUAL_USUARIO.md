# Manual de usuario de SINDESG

**Sistema de Inventario y Despacho de Servicios Generales**

Versión documental: Fase 14C

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
