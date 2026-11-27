# Diagrama de secuencia — 03 Gestionar venta

Archivo StarUML: [`03-gestionar-venta.mdj`](./03-gestionar-venta.mdj)

Para abrirlo: **StarUML → File → Open → `03-gestionar-venta.mdj`**. El diagrama está en
*Model Explorer → 03 Gestionar venta → 03 Gestionar venta (colaboración) → Gestionar Venta (interacción)*.

## Estructura

| Bloque | Contenido |
|---|---|
| Inicio (fuera de fragmentos) | El Cajero entra a Ventas y se muestra el listado (mensajes 1–6) |
| `seq` Crear venta | Cargar formulario, productos, métodos de pago y estado de caja; buscar caja abierta, validar stock, guardar venta y descontar stock (7–29) |
| `seq` Editar venta | Buscar venta, editar datos, verificar que la caja siga abierta, guardar y ajustar stock (30–44) |
| `seq` Ver venta (show) | Buscar venta y mostrar detalle (45–49) |
| `seq` Filtrar venta por fecha | Buscar ventas filtradas y mostrarlas (50–54) |

## Participantes (en el orden del diagrama)

Estilo tomado de `docs/Gestionar_Persona.png` y símbolos de `docs/Simbolos.jpeg` (estereotipos de robustez).
El archivo incluye el perfil **UML Standard Profile (v2)** de StarUML, necesario para que se dibujen los íconos boundary, control y entity.

| Línea de vida | Símbolo | Corresponde a |
|---|---|---|
| Cajero | actor | Usuario con rol Cajero o permiso `registrar_ventas` |
| Pantalla Principal | boundary | Panel y menú lateral (`layouts/app.blade.php`) |
| Index Venta | boundary | `resources/views/ventas/index.blade.php` |
| Create Venta | boundary | `resources/views/ventas/create.blade.php` |
| Edit Venta | boundary | `resources/views/ventas/corregir.blade.php` |
| Show Venta | boundary | `resources/views/ventas/show.blade.php` |
| VentaController | control | `app/Http/Controllers/VentaController.php` |
| Venta Model | entity | `app/Models/Venta.php` |
| Producto Model | entity | `app/Models/Producto.php` |
| MetodoPago Model | entity | `app/Models/MetodoPago.php` |
| Caja Model | entity | `app/Models/Caja.php` (la venta exige una caja `ABIERTA` en la sucursal) |

## Convención

- Flecha continua: llamada síncrona. Flecha punteada: respuesta.
- Mensaje en bucle sobre la misma línea de vida: operación interna (por ejemplo, «Obtener ventas», «Guardar venta»).
- `seq`: fragmento combinado de secuencia débil, uno por cada operación del caso de uso.
- El marco del diagrama es `interaction Gestionar Venta`.
- La Pantalla Principal permanece activa durante todo el caso de uso.
- La numeración de los mensajes la genera StarUML (`showSequenceNumber`).
