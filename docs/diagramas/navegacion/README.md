# Diagramas de navegación — Salteñería Cupesí

Archivo StarUML: [`navegacion-modulos.mdj`](./navegacion-modulos.mdj)

Contiene **13 diagramas**, uno por módulo, derivados de:

- `routes/web.php`
- `app/Http/Controllers/`
- `resources/views/layouts/app.blade.php`
- `Cuestionario-Jurado-Saltenieria-Cupesi.docx` (lista académica de módulos)

## Convención

- Cada pantalla es una clase UML con el estereotipo `«Pantalla»`.
- El atributo `ruta` muestra el nombre de la ruta Laravel correspondiente.
- Cada flecha es una asociación dirigida (`end2` navegable) etiquetada con la acción o ruta que provoca el cambio de pantalla.
- Las acciones `POST`, `PUT` y `PATCH` aparecen en la etiqueta de la flecha; no se representan como pantallas.
- Las notas indican permisos, contexto de sucursal, reglas de negocio y endpoints JSON que no son vistas.
- Los bucles sobre la misma clase indican acciones que recargan o permanecen en la misma pantalla.

Para abrirlo: **StarUML → File → Open → `navegacion-modulos.mdj`**.

## Índice de diagramas

| # | Diagrama | Pantallas principales |
|---|---|---|
| 01 | Autenticación y cambio de sucursal | Login → Selección de sucursal → Panel; logout |
| 02 | Panel de control | Menú lateral a los 11 módulos, cambio de sucursal y cierre de sesión |
| 03 | Ventas | Listado → Registrar → Detalle → Comprobante / Corregir |
| 04 | Productos e inventario | Listado → Formulario / Detalle; stock por sucursal |
| 05 | Categorías | Listado ↔ Formulario; reactivación |
| 06 | Métodos de pago | Listado ↔ Formulario; reactivación |
| 07 | Caja | Listado → Detalle y arqueo; apertura y cierre |
| 08 | Sucursales | Listado ↔ Formulario |
| 09 | Personal | Listado ↔ Formulario |
| 10 | Usuarios | Listado ↔ Formulario; restablecimiento de contraseña |
| 11 | Roles | Listado ↔ Formulario; sincronización de permisos |
| 12 | Permisos | Listado con alta y edición en línea |
| 13 | Reportes | Menú desplegable → 6 reportes → exportación PDF |

## Observaciones

- `inventario.index`, `inventario.producto`, `inventario.stock-bajo`, `inventario.stock-minimo` y `cajas.estado` devuelven JSON; se documentan en notas porque no son pantallas.
- Las rutas `*.pdf` generan un documento con Dompdf; se muestran como acciones sobre la pantalla del reporte.
- La visibilidad de cada módulo depende del rol `Administrador` o de la clave de permiso indicada en el menú lateral.
