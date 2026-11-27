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

---

# Diagramas de navegación por usuario — Cajero

El Cajero tiene dos diagramas, uno por caso de uso:

| Archivo | Contenido |
|---|---|
| [`navegacion-cajero-1-venta.mdj`](./navegacion-cajero-1-venta.mdj) | Gestionar venta |
| [`navegacion-cajero-2-caja.mdj`](./navegacion-cajero-2-caja.mdj) | Gestionar caja |

Para abrirlos: **StarUML → File → Open**. Cada archivo tiene un único diagrama en *Model Explorer*.

Sigue el estilo de los ejemplos `docs/Diagrama de navegacion gestionar reserva.png` y
`docs/Diagrama de navegacion gestionar tipopersona, gestionar persona, y gestionar persona.png`
(extensión WAE de Conallen). El archivo incluye un perfil *WAE (Web Application Extension)* con los estereotipos
`server page`, `client page` y `form`.

## Convención

| Elemento | Significado |
|---|---|
| `«server page»` | Ruta/acción del controlador Laravel (atributos `route`, `middleware`, campos recibidos; operaciones del controlador) |
| `«client page»` | Vista Blade que ve el usuario (`view`, tablas, enlaces y botones) |
| `«form»` | Formulario contenido en la página cliente (campos y botones) |
| `«build»` | La server page construye la client page |
| `«link GET/…»` | Enlace o botón que navega a otra ruta |
| `«submit»` | Envío del formulario a la server page |
| `«content»` (rombo negro) | La client page contiene el formulario |
| `«build redirect»` / `«redirect (fail)»` | Redirección tras éxito / regreso con errores de validación |

## Flujo del Cajero

1. **Autenticación:** `«GET/login»` → `ps_login` → `pc_login` + `form_login` (nombre_usuario, password) → `ps_login` (`login.store`).
   Con una sola sucursal se asigna automáticamente y se construye `pc_dashboard`. Desde el panel: cerrar sesión (`POST logout`).
2. **Gestionar venta:**
   - `Venta` (`ventas.index`) → `Venta Index` con el formulario `FiltroVentas` (desde, hasta).
   - Registrar: `VentaCreate` (`ventas.create`, verifica caja abierta) → formulario del carrito y pagos → `Venta Store` (`ventas.store`).
   - Detalle: `VentaShow` (`ventas.show`) → corregir (`ventas.corregir` → `Venta Update` `PUT ventas.corregir.update`) o comprobante (`ventas.receipt`).
3. **Gestionar caja:** `Caja` (`cajas.index`) → `Caja Index` con los formularios `AbrirCaja` (`POST cajas.abrir`),
   `FiltroCajas` (GET) y `CerrarCaja` (`POST cajas.cerrar`, arqueo con diferencias de efectivo y QR).

Notas del diagrama: el Cajero tiene una sola sucursal (no puede cambiarla) y solo puede registrar o corregir ventas con la caja **ABIERTA**.

---

# Diagramas de navegación por usuario — Administrador

El Administrador se divide en siete diagramas de dos módulos (o dos reportes) cada uno, para que sean legibles. Todos comparten el inicio:
`«GET/login»` → `ps_login` → `pc_login` + `form_login` → `ps_login` (`login.store`); con una sucursal va al panel y con varias
pasa por `ps_sucursal` (`branch.choose`) → `pc_sucursal` + `form_sucursal` → `branch.set` → `pc_dashboard`.
Desde el panel puede volver a elegir sucursal (`«link GET/sucursal-activa»`).

Ventas y Caja no se repiten aquí: el Administrador las usa igual que el Cajero (ver `navegacion-cajero-1-venta.mdj` y `navegacion-cajero-2-caja.mdj`).

| Archivo | Módulos | Contenido principal |
|---|---|---|
| [`navegacion-administrador-1-personas-usuarios.mdj`](./navegacion-administrador-1-personas-usuarios.mdj) | Personas, Usuarios | CRUD con fila «crear» (arriba) y «editar» (abajo), búsqueda de personas, contraseña opcional al editar usuario |
| [`navegacion-administrador-2-roles-permisos.mdj`](./navegacion-administrador-2-roles-permisos.mdj) | Roles, Permisos | Permisos del rol en su formulario, alta de permiso en página propia y edición en ventana modal |
| [`navegacion-administrador-3-sucursales-categorias.mdj`](./navegacion-administrador-3-sucursales-categorias.mdj) | Sucursales, Categorías | CRUD, botones Desactivar (`DELETE`) / Activar (`PATCH …restore`) |
| [`navegacion-administrador-4-productos-metodos-pago.mdj`](./navegacion-administrador-4-productos-metodos-pago.mdj) | Productos, Métodos de pago | Detalle de producto, filtro por nombre y categoría, stock por sucursal (`PATCH productos.stock`), Desactivar / Activar método de pago |
| [`navegacion-administrador-5-reportes-ventas.mdj`](./navegacion-administrador-5-reportes-ventas.mdj) | Reportes | Ventas por período, Ventas por sucursal (filtro + PDF) |
| [`navegacion-administrador-6-reportes-productos.mdj`](./navegacion-administrador-6-reportes-productos.mdj) | Reportes | Productos más vendidos, Existencias por sucursal (filtro + PDF) |
| [`navegacion-administrador-7-reportes-pagos-caja.mdj`](./navegacion-administrador-7-reportes-pagos-caja.mdj) | Reportes | Totales por método de pago, Historial de caja (filtro + PDF) y detalle de caja (arqueo) |

La convención (`«server page»`, `«client page»`, `«form»`, `«build»`, `«link»`, `«submit»`, `«content»`, redirecciones) es la misma
del diagrama del Cajero.
