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

---

# Diagrama de secuencia (estilo robustez) — Gestionar Caja

Archivo StarUML: [`07-gestionar-caja-robustez.mdj`](./07-gestionar-caja-robustez.mdj)

Para abrirlo: **StarUML → File → Open → `07-gestionar-caja-robustez.mdj`**. El diagrama está en
*Model Explorer → 07 Gestionar caja (robustez) → 07 Gestionar caja (robustez) (colaboración) → Gestionar Caja (interacción)*.

Mismo estilo que `docs/Gestionar_Persona.png` y `03-gestionar-venta.mdj`, con los símbolos de `docs/Simbolos.jpeg`.
El archivo incluye el perfil **UML Standard Profile (v2)** de StarUML, necesario para dibujar los íconos boundary, control y entity.

## Estructura

| Bloque | Contenido |
|---|---|
| Inicio (fuera de fragmentos) | El Cajero entra a Caja; se cargan cajas y sucursales y se muestra el listado (mensajes 1–9). Nota: las sucursales alimentan los selectores de abrir caja y filtro |
| `seq` Abrir caja | Validar datos, verificar que no haya caja abierta y guardar la apertura (10–15) |
| `seq` Filtrar cajas | Buscar y mostrar las cajas filtradas (16–20) |
| `seq` Cerrar caja | Buscar la caja y sus ventas, calcular diferencias del arqueo y guardar el cierre (21–32) |

## Participantes (en el orden del diagrama)

| Línea de vida | Símbolo | Corresponde a |
|---|---|---|
| Cajero | actor | Usuario con permiso `gestionar_caja` (Administrador o Cajero) |
| Pantalla Principal | boundary | Panel y menú lateral (`layouts/app.blade.php`) |
| Index Caja | boundary | `resources/views/cajas/index.blade.php` |
| CajaController | control | `app/Http/Controllers/CajaController.php` |
| Caja Model | entity | `app/Models/Caja.php` |
| Sucursal Model | entity | `app/Models/Sucursal.php` |
| Venta Model | entity | `app/Models/Venta.php` |

## Convención

- Flecha continua: llamada síncrona. Flecha punteada: respuesta.
- Mensaje en bucle sobre la misma línea de vida: operación interna.
- `seq`: fragmento combinado, uno por operación del caso de uso.
- El marco del diagrama es `interaction Gestionar Caja`; la Pantalla Principal permanece activa durante todo el caso de uso.
