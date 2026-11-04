1. Decisiones de diseño
•	Se incorpora personal para registrar trabajadores independientemente de si tienen cuenta de acceso. Una cuenta de usuario puede estar asociada a un trabajador (usuarios.id_personal); un trabajador tendrá como máximo una cuenta.
•	Usuarios y roles se relacionan M:N mediante usuario_rol; roles y permisos, M:N mediante rol_permiso. Los usuarios pueden trabajar en una o ambas sucursales mediante usuario_sucursal.
•	Auditoría: creado_el, modificado_el, eliminado_el y referencias creado_por, modificado_por, eliminado_por. La eliminación lógica registra eliminado_el y evita borrar registros administrativos con referencias. Los campos *_por pueden ser NULL para cargas iniciales automáticas. Los nombres de registros eliminados lógicamente no se pueden reutilizar porque se mantienen las restricciones UNIQUE; el registro existente debe reactivarse.
•	No se incluyen módulos de clientes, delivery, transferencias de stock ni backup. Los reportes y el panel de control se generan mediante consultas, sin tablas independientes.
•	Las claves foráneas se presentan al final de cada tabla; las tablas puente mantienen juntas las claves que forman su PK compuesta.
•	Los campos de auditoría de tablas transaccionales (cajas y ventas) registran creación/modificación. Las ventas confirmadas no deben eliminarse físicamente; cualquier anulación futura requeriría reglas explícitas.
2. Tablas y atributos
2.1  roles
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_rol	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(30)	NN, UQ, USR	Nombre del rol
descripcion	VARCHAR(100)	NULL, USR	Responsabilidades
activo	BOOLEAN	NN, USR	Estado; predeterminado TRUE
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_el	TIMESTAMPTZ	NULL	Última modificación
eliminado_el	TIMESTAMPTZ	NULL	Fecha de eliminación lógica
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
eliminado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que realizó eliminación lógica

2.2  permisos
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_permiso	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(30)	NN, UQ, SIST	Nombre único del permiso, ej. registrar_ventas
descripcion	VARCHAR(100)	NULL, SIST	Alcance del permiso
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_el	TIMESTAMPTZ	NULL	Última modificación
eliminado_el	TIMESTAMPTZ	NULL	Fecha de eliminación lógica
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
eliminado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que realizó eliminación lógica

2.3  rol_permiso
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_rol	BIGINT	PK compuesta, FK	Referencia a roles
id_permiso	BIGINT	PK compuesta, FK	Referencia a permisos

2.4  personal
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_personal	BIGINT	PK, AUTO	Identificador de trabajador
nombres	VARCHAR(30)	NN	Nombres del trabajador
apellidos	VARCHAR(30)	NN	Apellidos del trabajador
ci	VARCHAR(20)	UNIQUE, NULL	Documento de identidad, si corresponde
telefono	VARCHAR(15)	NULL	Teléfono de contacto
activo	BOOLEAN	NN, DEFAULT true	Indica si el personal está activo
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_el	TIMESTAMPTZ	NULL	Última modificación
eliminado_el	TIMESTAMPTZ	NULL	Fecha de eliminación lógica
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
eliminado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que realizó eliminación lógica

2.5  usuarios
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_usuario	BIGINT	PK, AUTO	Identificador
nombre_usuario	VARCHAR(30)	NN, UQ, USR	Identificador de inicio de sesión
password_hash	VARCHAR(255)	NN, SIST	Hash seguro generado mediante Laravel Hash; nunca guardar contraseña en texto plano
activo	BOOLEAN	NN, USR	Estado de cuenta
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_el	TIMESTAMPTZ	NULL	Última modificación
eliminado_el	TIMESTAMPTZ	NULL	Fecha de eliminación lógica
id_personal	BIGINT	FK → personal.id_personal, UNIQUE, NULL	Trabajador asociado a la cuenta; nulo si cuenta técnica
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
eliminado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que realizó eliminación lógica

2.6  usuario_rol
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_usuario	BIGINT	PK compuesta, FK → usuarios.id_usuario	Usuario al que se asigna el rol
id_rol	BIGINT	PK compuesta, FK → roles.id_rol	Rol asignado al usuario

2.7  sucursales
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_sucursal	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(50)	NN, UQ, USR	Nombre de sucursal
direccion	VARCHAR(150)	NN, USR	Ubicación
telefono	VARCHAR(15)	NULL, USR	Número de contacto
activo	BOOLEAN	NN, SIST	Estado operativo; propuesta
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_el	TIMESTAMPTZ	NULL	Última modificación
eliminado_el	TIMESTAMPTZ	NULL	Fecha de eliminación lógica
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
eliminado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que realizó eliminación lógica

2.8  usuario_sucursal
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
fecha_asignacion	DATE	NN, DEFAULT CURRENT_DATE	Inicio de asignación
fecha_fin	DATE	NULL, USR	Fin de asignación; NULL si vigente
id_usuario	BIGINT	PK compuesta, FK	Usuario asignado
id_sucursal	BIGINT	PK compuesta, FK	Sucursal autorizada

2.9  categorias
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_categoria	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(30)	NN, UQ, USR	Nombre de categoría
descripcion	VARCHAR(100)	NULL, USR	Descripción
activo	BOOLEAN	NN, USR	Estado
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_el	TIMESTAMPTZ	NULL	Última modificación
eliminado_el	TIMESTAMPTZ	NULL	Fecha de eliminación lógica
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
eliminado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que realizó eliminación lógica

2.10  productos
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_producto	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(50)	NN, USR	Nombre comercial
descripcion	VARCHAR(100)	NULL, USR	Descripción
precio	NUMERIC(10,2)	NN, USR	Precio actual; mayor que cero
activo	BOOLEAN	NN, USR	Estado
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_el	TIMESTAMPTZ	NULL	Última modificación
eliminado_el	TIMESTAMPTZ	NULL	Fecha de eliminación lógica
id_categoria	BIGINT	FK, NN, USR	Categoría
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
eliminado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que realizó eliminación lógica

2.11  producto_sucursal
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
stock_actual	INTEGER	NN, USR/SIST	Existencias ≥ 0; ajustadas por administrador o ventas
stock_minimo	INTEGER	NN, USR	Umbral de alerta ≥ 0; propuesta
id_producto	BIGINT	PK compuesta, FK	Producto
id_sucursal	BIGINT	PK compuesta, FK	Sucursal

2.12  metodos_pago
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_metodo_pago	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(30)	NN, UQ, USR	Efectivo, QR, transferencia bancaria
activo	BOOLEAN	NN, USR	Disponible para ventas
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_el	TIMESTAMPTZ	NULL	Última modificación
eliminado_el	TIMESTAMPTZ	NULL	Fecha de eliminación lógica
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
eliminado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que realizó eliminación lógica

2.13  cajas
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_caja	BIGINT	PK, AUTO	Identificador de cajas
fecha_apertura	TIMESTAMPTZ	NN, AUTO	Momento de apertura
fecha_cierre	TIMESTAMPTZ	NULL, AUTO	Momento de cierre
monto_inicial	NUMERIC(12,2)	NN, USR	Efectivo inicial ≥ 0
efectivo_contado	NUMERIC(12,2)	NULL, USR	Conteo manual al cierre ≥ 0
efectivo_esperado	NUMERIC(12,2)	NULL, CALC	Inicial + pagos en efectivo
diferencia	NUMERIC(12,2)	NULL, CALC	Contado − esperado
estado	VARCHAR(15)	NN, SIST	ABIERTA o CERRADA
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_el	TIMESTAMPTZ	NULL	Última modificación
id_sucursal	BIGINT	FK, NN, SIST	Sucursal de la caja
id_usuario_apertura	BIGINT	FK, NN, SIST	Responsable de apertura
id_usuario_cierre	BIGINT	FK, NULL, SIST	Responsable de cierre
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro

2.14  ventas
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_venta	BIGINT	PK, AUTO	Identificador
fecha_venta	TIMESTAMPTZ	NN, AUTO	Fecha y hora
total	NUMERIC(12,2)	NN, CALC	Suma de subtotales; ≥ 0
estado	VARCHAR(15)	NN, SIST	CONFIRMADA; ampliable si se diseñan anulaciones
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_el	TIMESTAMPTZ	NULL	Última modificación
id_sucursal	BIGINT	FK, NN, SIST	Sucursal de venta
id_usuario	BIGINT	FK, NN, SIST	Usuario que registró la venta
id_caja	BIGINT	FK, NN, SIST	Caja abierta asociada
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro

2.15  detalle_venta
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_detalle_venta	BIGINT	PK, AUTO	Identificador de línea
cantidad	INTEGER	NN, USR	Cantidad > 0
precio_unitario	NUMERIC(10,2)	NN, SIST	Precio capturado al vender
subtotal	NUMERIC(12,2)	NN, CALC	Cantidad × precio_unitario
id_venta	BIGINT	FK, NN, SIST	Venta
id_producto	BIGINT	FK, NN, USR	Producto seleccionado

2.16  venta_pago
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_venta_pago	BIGINT	PK, AUTO	Identificador del pago de la venta
importe	NUMERIC(12,2)	NN, USR	Importe imputado a venta > 0
verificado	BOOLEAN	NN, USR/SIST	Confirmación manual para QR/transferencia; propuesta
id_venta	BIGINT	FK, NN, SIST	Venta
id_metodo_pago	BIGINT	FK, NN, USR	Método elegido

3. Relaciones principales
•	personal (1) — (0..1) usuarios, mediante usuarios.id_personal (UNIQUE).
•	usuarios (M) — (N) roles, mediante usuario_rol.
•	roles (M) — (N) permisos, mediante rol_permiso.
•	usuarios (M) — (N) sucursales, mediante usuario_sucursal.
•	categorias (1) — (N) productos.
•	productos (M) — (N) sucursales, mediante producto_sucursal.
•	sucursales (1) — (N) ventas; usuarios (1) — (N) ventas.
•	sucursales (1) — (N) cajas; una sucursal puede tener múltiples registros históricos, pero como máximo una caja con estado ABIERTA.
•	cajas (1) — (N) ventas; ventas (1) — (N) detalle_venta y venta_pago.
•	metodos_pago (1) — (N) venta_pago.
4. Reglas para implementación
•	Para evitar referencias circulares durante las migraciones, crear primero las tablas principales y luego agregar las FK de auditoría hacia usuarios en migraciones posteriores.
•	Las operaciones de venta y descuento de stock deben ejecutarse en una única transacción; no permitir stock negativo.
•	La suma de venta_pago.importe debe coincidir exactamente con ventas.total. Una venta puede combinar efectivo, QR y transferencia bancaria. La conciliación de caja física solo suma pagos en efectivo.
•	El stock pertenece a producto_sucursal, no a productos, porque cada sucursal tiene existencias independientes.
•	No se debe confundir el campo activo con eliminado_el: activo representa habilitación; eliminado_el representa eliminación lógica.
•	usuario_sucursal conserva la clave primaria compuesta (id_usuario, id_sucursal) y no implementa historial de múltiples asignaciones del mismo par. fecha_asignacion utiliza DEFAULT CURRENT_DATE.
•	Una sucursal podrá tener como máximo una caja abierta simultáneamente. Esta regla se implementará en PostgreSQL mediante un índice único parcial sobre id_sucursal condicionado a estado = 'ABIERTA'. Los únicos estados permitidos para cajas son ABIERTA y CERRADA.
•	El efectivo esperado se calcula como monto_inicial + pagos en efectivo, y la diferencia como efectivo_contado − efectivo_esperado. Los pagos QR y transferencia bancaria no aumentan el efectivo esperado. Ambos valores se calculan y muestran después de guardar el cierre.
•	La recuperación automática de contraseñas por correo electrónico está fuera del alcance. El restablecimiento será realizado por un administrador autorizado; usuarios utiliza nombre_usuario y password_hash.
•	La contraseña ingresada debe tener entre 8 y 255 caracteres. Este límite se aplica a la contraseña original, no al hash almacenado en password_hash.
•	Las contraseñas se almacenan exclusivamente como hashes generados mediante Laravel Hash; nunca se almacenan en texto plano.
•	El diseño de rol múltiple reemplaza la decisión anterior de un rol por usuario; la autorización debe comprobar los permisos acumulados de todos sus roles.
    
