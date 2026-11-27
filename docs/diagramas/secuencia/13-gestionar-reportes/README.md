# Diagrama de secuencia — 13 Gestionar reportes

Archivo StarUML: [`13-gestionar-reportes.mdj`](./13-gestionar-reportes.mdj)

Para abrirlo: **StarUML → File → Open → `13-gestionar-reportes.mdj`**. El diagrama está en
*Model Explorer → 13 Gestionar reportes → 13 Gestionar reportes (colaboración) → Gestionar Reportes (interacción)*.

Mismo estilo que `docs/Gestionar_Persona.png`, con los símbolos de `docs/Simbolos.jpeg`.
El archivo incluye el perfil **UML Standard Profile (v2)** de StarUML, necesario para dibujar los íconos boundary, control y entity.

## Estructura

| Bloque | Contenido |
|---|---|
| Inicio (fuera de fragmentos) | El Administrador entra a Reportes; se cargan las sucursales (mensajes 1–6). Nota: se cargan una sola vez porque alimentan el filtro de todos los reportes |
| `seq` Historial de caja | Listar cajas, contar abiertas y cerradas, filtrar y ver detalle (7–23) |
| `seq` Totales por método de pago | Totales y participación por método, sin filtro y filtrados (24–35) |
| `seq` Productos más vendidos | Ranking de productos, sin filtro y filtrado (36–45) |
| `seq` Ventas por sucursal | Comparativo y participación por sucursal, sin filtro y filtrado (46–57) |
| `seq` Ventas por período | Ventas y totales del período, sin filtro y filtradas (58–69) |
| `seq` Exportar PDF | Generar y descargar el PDF del reporte (70–72) |

## Participantes (en el orden del diagrama)

| Línea de vida | Símbolo | Corresponde a |
|---|---|---|
| Administrador | actor | Usuario con rol Administrador o permiso `generar_reportes` |
| Pantalla Principal | boundary | Panel y menú lateral Reportes (`layouts/app.blade.php`) |
| Reporte Caja | boundary | `resources/views/reportes/cajas.blade.php` |
| Reporte Metodo Pago | boundary | `resources/views/reportes/pagos.blade.php` |
| Reporte Productos | boundary | `resources/views/reportes/productos-mas-vendidos.blade.php` |
| Reporte Ventas Sucursal | boundary | `resources/views/reportes/ventas-sucursal.blade.php` |
| Reporte Ventas Periodo | boundary | `resources/views/reportes/ventas-periodo.blade.php` (PDF: `pdf.blade.php`) |
| ReporteController | control | `app/Http/Controllers/ReporteController.php` |
| Sucursal Model | entity | `app/Models/Sucursal.php` |
| Caja Model | entity | `app/Models/Caja.php` |
| Pago Model | entity | `app/Models/VentaPago.php` |
| DetalleVenta Model | entity | `app/Models/DetalleVenta.php` |
| Venta Model | entity | `app/Models/Venta.php` |

## Convención

- Flecha continua: llamada síncrona. Flecha punteada: respuesta.
- Mensaje en bucle sobre la misma línea de vida: operación interna (por ejemplo, «Calcular participación», «Generar PDF»).
- `seq`: fragmento combinado, uno por reporte. Los fragmentos que empiezan en la Pantalla Principal la incluyen dentro del marco.
- El marco del diagrama es `interaction Gestionar Reportes`; la Pantalla Principal permanece activa durante todo el caso de uso.
