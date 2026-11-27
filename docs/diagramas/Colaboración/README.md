# Diagrama de colaboración — Gestionar Sucursal

Archivo StarUML: [`gestionar-sucursal-colaboracion.mdj`](./gestionar-sucursal-colaboracion.mdj)

Para abrirlo: **StarUML → File → Open → `gestionar-sucursal-colaboracion.mdj`**. El diagrama está en
*Model Explorer → Gestionar Sucursal (colaboración) → Gestionar Sucursal (colaboración) → Gestionar Sucursal (interacción)*.

Se obtuvo del diagrama de secuencia [`Gestionar_Sucursal_sin_marca.png`](./Gestionar_Sucursal_sin_marca.png): tiene los mismos objetos y los mismos
mensajes, con la misma numeración (1–26). El estilo sigue el ejemplo de esta carpeta (íconos boundary, control y entity
y un solo enlace por cada par de objetos). El archivo incluye el perfil **UML Standard Profile** de StarUML, que dibuja esos íconos.

## Objetos

| Objeto | Símbolo | Corresponde a |
|---|---|---|
| Administrador | actor | Usuario con rol Administrador |
| Pantalla Principal | boundary | Panel principal y menú lateral (`layouts/app.blade.php`) |
| Index Sucursal | boundary | `resources/views/sucursales/index.blade.php` |
| Create Sucursal | boundary | Formulario de registro de sucursal |
| Edit Sucursal | boundary | Formulario de edición de sucursal |
| SucursalController | control | `app/Http/Controllers/SucursalController.php` |
| SucursalModel | entity | `app/Models/Sucursal.php` |

## Mensajes por enlace

| Enlace | Mensajes |
|---|---|
| Administrador — Pantalla Principal | 1 Ir a inicio |
| Pantalla Principal — SucursalController | 2 Obtener sucursales |
| SucursalController — SucursalModel | 3 Buscar sucursales, 12 Validar datos, 17 Buscar sucursal, 23 Validar datos · respuestas: 5 Retornar sucursales, 14 Sucursal guardada, 19 Retornar sucursal, 25 Sucursal editada |
| SucursalModel (a sí mismo) | 4 Obtener sucursales, 13 Guardar datos, 18 Obtener sucursal, 24 Guardar datos |
| SucursalController — Index Sucursal | 6 Mostrar sucursales, 15 Lista actualizada, 26 Lista actualizada · desde Index: 7 Registrar sucursal, 16 Editar sucursal |
| SucursalController (a sí mismo) | 8 Cargar formulario |
| SucursalController — Create Sucursal | 9 Abrir formulario · desde Create: 11 Enviar datos |
| Create Sucursal (a sí mismo) | 10 Llenar datos |
| SucursalController — Edit Sucursal | 20 Abrir formulario · desde Edit: 22 Enviar datos |
| Edit Sucursal (a sí mismo) | 21 Editar datos |

## Convención

- Cada mensaje lleva una flecha que indica su sentido sobre el enlace. Los números dan el orden en que ocurren.
- Los mensajes del fragmento `seq Registrar Sucursal` son del 7 al 15 y los de `seq Editar Sucursal`, del 16 al 26.

---

# Diagrama de colaboración — Gestionar Categoría

Archivo StarUML: [`gestionar-categoria-colaboracion.mdj`](./gestionar-categoria-colaboracion.mdj)

Para abrirlo: **StarUML → File → Open → `gestionar-categoria-colaboracion.mdj`**. El diagrama está en
*Model Explorer → Gestionar Categoría (colaboración) → Gestionar Categoría (colaboración) → Gestionar Categoría (interacción)*.

Se obtuvo del diagrama de secuencia [`Gestionar_Categoria_sin_marca.png`](./Gestionar_Categoria_sin_marca.png): tiene los mismos objetos y los mismos
mensajes, con la misma numeración (1–20). Sigue el mismo estilo y la misma disposición que el de Gestionar Sucursal.

## Objetos

| Objeto | Símbolo | Corresponde a |
|---|---|---|
| Administrador | actor | Usuario con rol Administrador |
| Pantalla Principal | boundary | Panel principal y menú lateral (`layouts/app.blade.php`) |
| Index Categoria | boundary | `resources/views/categorias/index.blade.php` |
| Create Categoria | boundary | Formulario de registro (`resources/views/categorias/form.blade.php`) |
| Edit Categoria | boundary | Formulario de edición (`resources/views/categorias/form.blade.php`) |
| CategoríaController | control | `app/Http/Controllers/CategoriaController.php` |
| CategoriaModel | entity | `app/Models/Categoria.php` |

## Mensajes por enlace

| Enlace | Mensajes |
|---|---|
| Administrador — Pantalla Principal | 1 Ir a inicio |
| Pantalla Principal — CategoríaController | 2 Obtener categoria |
| CategoríaController — CategoriaModel | 3 Buscar categoría, 10 Guardar categoría, 17 Guardar cambios · respuestas: 5 Categorias disponibles, 12 Categoría creada, 19 Categoría editada |
| CategoriaModel (a sí mismo) | 4 Categoria encontrada, 11 Verificando, 18 Verificando |
| CategoríaController — Index Categoria | 6 Mostrar categorías, 13 Actualizar categoría, 20 Listar categoría · desde Index: 7 Crear categoría, 14 Editar categoría |
| CategoríaController — Create Categoria | 8 Abrir formulario · desde Create: 9 Enviar datos |
| CategoríaController — Edit Categoria | 15 Abrir editar categoría · desde Edit: 16 Enviar datos |

Los mensajes del fragmento `seq Crear Categoría` son del 7 al 13 y los de `seq Editar Categoría`, del 14 al 20.
