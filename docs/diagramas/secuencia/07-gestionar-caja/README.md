# Diagramas de secuencia — 07 Gestionar caja

Archivo StarUML: [`07-gestionar-caja.mdj`](./07-gestionar-caja.mdj)

Para abrirlo: **StarUML → File → Open → `07-gestionar-caja.mdj`**. Cada diagrama está en
*Model Explorer → 07 Gestionar caja → <colaboración> → <interacción>*.

Derivado de:

- `app/Http/Controllers/CajaController.php`
- `app/Http/Controllers/Concerns/AuthorizesSistema.php`
- `app/Support/CajaCalculadora.php`
- `resources/views/cajas/index.blade.php`, `resources/views/cajas/show.blade.php`
- `routes/web.php`

## Índice

| # | Diagrama | Ruta | Método |
|---|---|---|---|
| 07.01 | Consultar cajas | `GET /cajas` | `index()` |
| 07.02 | Abrir caja | `POST /cajas/abrir` | `abrir()` |
| 07.03 | Cerrar caja (arqueo) | `POST /cajas/{caja}/cerrar` | `cerrar()` |
| 07.04 | Consultar detalle de caja | `GET /cajas/{caja}` | `show()` |

## Participantes

Usuario (actor) · vista Blade · `CajaController` · `AuthorizesSistema` · modelos Eloquent (`Caja`, `Sucursal`, `Venta`) · `CajaCalculadora` · Base de datos.

## Convención

- Flecha continua: llamada síncrona. Flecha punteada: respuesta.
- `alt`: caminos alternativos (permiso, validación, caja ya abierta o cerrada, JSON o vista).
- `opt`: pasos que solo ocurren si se envía el filtro.
- La apertura y el cierre se ejecutan dentro de `DB::transaction()` con `lockForUpdate()`, para que no haya dos cajas abiertas en una sucursal ni dos cierres de la misma caja.
- `GET /cajas/estado/actual` (`estado()`) devuelve JSON y aparece como nota en 07.01.
