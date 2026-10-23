# SINDESG

**Sistema de Inventario y Despacho de Servicios Generales**

SINDESG es el sistema institucional para controlar la recepción, clasificación, inventario, distribución y despacho de uniformes, prendas y otros equipos manejados por Servicios Generales de la Policía Nacional.

## Objetivo

SINDESG debe cubrir la cadena logística completa:

1. Recepción de artículos por lotes.
2. Registro de artículos comprados, transferidos, donados o confeccionados internamente.
3. Clasificación por categoría, producto, variante, talla, marca, código y lote.
4. Control de existencias del Almacén Central.
5. Solicitudes de Direcciones, Zonas y dependencias.
6. Distribución del Almacén Central hacia almacenes/P4.
7. Control de inventario propio de cada P4.
8. Despacho individual del P4 hacia funcionarios.
9. Cambios, devoluciones, bajas, conteos y ajustes autorizados.
10. Trazabilidad y auditoría completa.
11. Reportes operativos y gerenciales.

## Tecnología acordada

- PHP
- MariaDB
- Apache mediante XAMPP
- HTML / CSS / JavaScript
- Git + GitHub
- Desarrollo local esperado en: `C:\xampp\htdocs\SINDESG`
- Repositorio: `rrene7/SINDESG`

SINDESG tendrá su propia base de datos en la misma instancia MariaDB donde se encuentra RRHH2029. La integración con RRHH2029 será de **solo lectura**.

## Principio de integración con RRHH

RRHH2029 continúa siendo la fuente maestra para:

- funcionarios;
- posiciones;
- rangos;
- estados laborales;
- estructura institucional y territorial;
- adscripción actual del funcionario.

SINDESG será la fuente maestra para:

- artículos;
- categorías y variantes;
- lotes;
- inventario;
- almacenes/P4;
- solicitudes;
- transferencias;
- despachos;
- devoluciones;
- ajustes;
- auditoría logística.

SINDESG no debe crear manualmente una segunda versión de un funcionario que ya existe en RRHH.

## Principio de inventario

La existencia no se modifica manualmente como un número aislado. Debe ser el resultado de movimientos trazables:

```text
Inventario =
Entradas
+ Transferencias recibidas
+ Devoluciones
- Transferencias enviadas
- Despachos
- Bajas
± Ajustes autorizados
```

Una transferencia del Almacén Central a un P4 no es consumo final. El consumo final ocurre cuando el P4 entrega el artículo al funcionario o se registra una salida definitiva autorizada.

## Experiencia visual

La pantalla principal será un dashboard interactivo de apariencia moderna, tecnológica y policial. Las categorías y productos serán interactivos y conducirán a sus módulos de gestión.

Las fotografías de los artículos serán fotografías reales cargadas por los usuarios autorizados. Los nombres, categorías y variantes iniciales se construirán a partir de los inventarios y registros existentes, luego serán administrados desde el catálogo maestro.

## Documentación

- [Hoja de ruta](docs/ROADMAP.md)
- [Arquitectura](docs/ARQUITECTURA.md)
- [Modelo funcional](docs/MODELO_FUNCIONAL.md)
- [Integración con RRHH2029](docs/INTEGRACION_RRHH.md)
- [Flujo de desarrollo y actualización local](docs/DESARROLLO.md)
- [Respaldo y recuperación](docs/RESPALDO_RECUPERACION.md)
- [Manual técnico](docs/MANUAL_TECNICO.md)
- [Manual de usuario](docs/MANUAL_USUARIO.md)
- [Despliegue institucional](docs/DESPLIEGUE_INSTITUCIONAL.md)

## Estado

**Fase 14 — estabilización y puesta en producción.**

Antes de cada bloque de desarrollo:

1. verificar el estado actual de `main`;
2. implementar un bloque limitado;
3. validar sintaxis, base de datos y pruebas disponibles;
4. subir el cambio a GitHub;
5. confirmar el commit publicado;
6. solo entonces actualizar la copia local en XAMPP.


### Linux institucional (sin XAMPP)

La nueva publicación se prepara para Nginx + PHP-FPM con `root /srv/sindesg/public`. La ruta web, los recursos y las API se resuelven automáticamente para Windows/XAMPP y Linux, también si la aplicación vive bajo un prefijo. No depende de letras de unidad de Windows.

Guía: [Despliegue Linux](docs/DESPLIEGUE_LINUX.md). Antes de exponer datos, validar HTTPS, rutas públicas, archivos privados, PHP-FPM, permisos y cuentas de MariaDB. RRHH2029 siempre con cuenta de solo lectura.
