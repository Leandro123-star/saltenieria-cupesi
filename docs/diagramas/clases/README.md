# Diagramas de clases

| Archivo | Contenido |
|---|---|
| [`bd-clases.mdj`](./bd-clases.mdj) | Modelo de la base de datos |
| [`dominio-final.mdj`](./dominio-final.mdj) | **Dominio final**: evolución del dominio inicial (`docs/Diagramaclase.jpeg`) con todos los atributos |

Para abrirlos: **StarUML → File → Open**.

## Dominio final

Mantiene la distribución y las clases asociación del dominio inicial
(`Usuario_Sucursal`, `Rol_Permiso`, `Producto_Sucursal`, `Detalle_Venta`, `Venta_Pago`) y agrega todos los atributos
tomados del esquema real (migraciones aplicadas en `saltenieria_test`).

### Cambios respecto al dominio inicial

1. **Nueva clase `Venta_Correccion`**: auditoría de cada corrección de venta (motivo, totales anterior y posterior,
   datos anteriores y posteriores en JSON, rol). Se relaciona con `Venta` (1 — *), `Usuario` (1 — *, registra),
   `Sucursal` (1 — *) y `Caja` (0..1 — *).
2. **`Caja` — `Usuario` se divide en dos asociaciones**: quien la **abre** (1 — *) y quien la **cierra** (0..1 — *).
3. **Atributos agregados durante el desarrollo**: `Producto.foto`, `Permiso.clave` (clave interna estable),
   arqueo QR en `Caja` (`qr_verificado`, `qr_esperado`, `diferencia_qr`, `diferencia_total`) y `reemplazado_el`
   en `Detalle_Venta` y `Venta_Pago` (líneas sustituidas por una corrección).

### Convención

- Las claves foráneas se representan con asociaciones; por eso no aparecen como atributos.
- `creado_por` / `modificado_por` son columnas de auditoría que referencian a `Usuario`; se muestran como atributos
  para no recargar el diagrama con trece asociaciones adicionales.
- Tipos: `int`, `string(n)`, `decimal(p,s)`, `boolean`, `date`, `datetime`, `text`, `json`.
