1. Decisiones de diseño
•	Se incorpora personas para registrar a los individuos independientemente de si tienen cuenta de acceso. Una cuenta de usuario debe estar asociada a una persona (usuarios.id_persona, UNIQUE); una persona tendrá como máximo una cuenta.
•	Cada usuario tiene exactamente un rol mediante usuarios.id_rol (relación 1:N). Roles y permisos se relacionan N:M mediante rol_permiso. Los usuarios pueden trabajar en una o ambas sucursales mediante usuario_sucursal.
•	Auditoría al final de cada tabla, en orden creado_por, creado_el, modificado_por, modificado_el. No existe eliminación lógica: la desactivación se representa con el campo activo. Los campos *_por pueden ser NULL para cargas iniciales automáticas.
•	No se incluyen módulos de clientes, delivery, transferencias de stock ni backup. Los reportes y el panel de control se generan mediante consultas, sin tablas independientes.
•	Las claves foráneas de relaciones 1:N se presentan antes de los campos de auditoría; las tablas puente mantienen juntas las claves que forman su PK compuesta.
•	Los campos de auditoría de tablas transaccionales (cajas y ventas) registran creación/modificación. Las ventas confirmadas no deben eliminarse físicamente; cualquier anulación futura requeriría reglas explícitas.
2. Tablas y atributos
2.1  roles
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_rol	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(30)	NN, UQ, USR	Nombre del rol
descripcion	VARCHAR(100)	NULL, USR	Responsabilidades
activo	BOOLEAN	NN, USR	Estado; predeterminado TRUE
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
modificado_el	TIMESTAMPTZ	NULL	Última modificación

2.2  permisos
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_permiso	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(30)	NN, UQ, SIST	Nombre único del permiso, ej. registrar_ventas
descripcion	VARCHAR(100)	NULL, SIST	Alcance del permiso
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
modificado_el	TIMESTAMPTZ	NULL	Última modificación

2.3  rol_permiso
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_rol	BIGINT	PK compuesta, FK → roles.id_rol	Referencia a roles
id_permiso	BIGINT	PK compuesta, FK → permisos.id_permiso	Referencia a permisos

2.4  personas
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_persona	BIGINT	PK, AUTO	Identificador de persona
nombres	VARCHAR(30)	NN	Nombres de la persona
apellidos	VARCHAR(30)	NN	Apellidos de la persona
ci	VARCHAR(20)	UNIQUE, NULL	Documento de identidad, si corresponde
telefono	VARCHAR(15)	NULL	Teléfono de contacto
activo	BOOLEAN	NN, DEFAULT true	Indica si la persona está activa
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
modificado_el	TIMESTAMPTZ	NULL	Última modificación

2.5  usuarios
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_usuario	BIGINT	PK, AUTO	Identificador
nombre_usuario	VARCHAR(30)	NN, UQ, USR	Identificador de inicio de sesión
password_hash	VARCHAR(255)	NN, SIST	Hash seguro generado mediante Laravel Hash; nunca guardar contraseña en texto plano
activo	BOOLEAN	NN, USR	Estado de cuenta
id_persona	BIGINT	FK → personas.id_persona, NN, UNIQUE	Persona asociada a la cuenta; obligatoria
id_rol	BIGINT	FK → roles.id_rol, NN	Rol único del usuario
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
modificado_el	TIMESTAMPTZ	NULL	Última modificación

2.6  sucursales
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_sucursal	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(50)	NN, UQ, USR	Nombre de sucursal
direccion	VARCHAR(150)	NN, USR	Ubicación
telefono	VARCHAR(15)	NULL, USR	Número de contacto
activo	BOOLEAN	NN, SIST	Estado operativo; propuesta
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
modificado_el	TIMESTAMPTZ	NULL	Última modificación

2.7  usuario_sucursal
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_usuario	BIGINT	PK compuesta, FK → usuarios.id_usuario	Usuario asignado
id_sucursal	BIGINT	PK compuesta, FK → sucursales.id_sucursal	Sucursal autorizada
fecha_asignacion	DATE	NN, DEFAULT CURRENT_DATE	Inicio de asignación
fecha_fin	DATE	NULL, USR	Fin de asignación; NULL si vigente

2.8  categorias
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_categoria	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(30)	NN, UQ, USR	Nombre de categoría
descripcion	VARCHAR(100)	NULL, USR	Descripción
activo	BOOLEAN	NN, USR	Estado
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
modificado_el	TIMESTAMPTZ	NULL	Última modificación

2.9  productos
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_producto	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(50)	NN, USR	Nombre comercial
descripcion	VARCHAR(100)	NULL, USR	Descripción
foto	VARCHAR(255)	NULL, USR	Ruta o nombre del archivo de imagen
precio	NUMERIC(10,2)	NN, USR	Precio actual; mayor que cero
activo	BOOLEAN	NN, USR	Estado
id_categoria	BIGINT	FK → categorias.id_categoria, NN, USR	Categoría
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
modificado_el	TIMESTAMPTZ	NULL	Última modificación

2.10  producto_sucursal
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_producto	BIGINT	PK compuesta, FK → productos.id_producto	Producto
id_sucursal	BIGINT	PK compuesta, FK → sucursales.id_sucursal	Sucursal
stock_actual	INTEGER	NN, USR/SIST	Existencias ≥ 0; ajustadas por administrador o ventas
stock_minimo	INTEGER	NN, USR	Umbral de alerta ≥ 0; propuesta

2.11  metodos_pago
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_metodo_pago	BIGINT	PK, AUTO	Identificador
nombre	VARCHAR(30)	NN, UQ, USR	Efectivo, QR, transferencia bancaria
activo	BOOLEAN	NN, USR	Disponible para ventas
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
modificado_el	TIMESTAMPTZ	NULL	Última modificación

2.12  cajas
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_caja	BIGINT	PK, AUTO	Identificador de cajas
fecha_apertura	TIMESTAMPTZ	NN, AUTO	Momento de apertura
fecha_cierre	TIMESTAMPTZ	NULL, AUTO	Momento de cierre
monto_inicial	NUMERIC(12,2)	NN, USR	Efectivo inicial ≥ 0
efectivo_contado	NUMERIC(12,2)	NULL, USR	Conteo manual al cierre ≥ 0
efectivo_esperado	NUMERIC(12,2)	NULL, CALC	Inicial + pagos en efectivo
diferencia	NUMERIC(12,2)	NULL, CALC	Contado − esperado
qr_verificado	NUMERIC(12,2)	NULL, USR	Monto QR verificado manualmente ≥ 0
qr_esperado	NUMERIC(12,2)	NULL, CALC	Pagos QR registrados
diferencia_qr	NUMERIC(12,2)	NULL, CALC	QR verificado − QR esperado
diferencia_total	NUMERIC(12,2)	NULL, CALC	Diferencia de efectivo + diferencia de QR
estado	VARCHAR(15)	NN, SIST	ABIERTA o CERRADA
id_sucursal	BIGINT	FK → sucursales.id_sucursal, NN, SIST	Sucursal de la caja
id_usuario_apertura	BIGINT	FK → usuarios.id_usuario, NN, SIST	Responsable de apertura
id_usuario_cierre	BIGINT	FK → usuarios.id_usuario, NULL, SIST	Responsable de cierre
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
modificado_el	TIMESTAMPTZ	NULL	Última modificación

2.13  ventas
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_venta	BIGINT	PK, AUTO	Identificador
fecha_venta	TIMESTAMPTZ	NN, AUTO	Fecha y hora
total	NUMERIC(12,2)	NN, CALC	Suma de subtotales; ≥ 0
estado	VARCHAR(15)	NN, SIST	CONFIRMADA; ampliable si se diseñan anulaciones
id_sucursal	BIGINT	FK → sucursales.id_sucursal, NN, SIST	Sucursal de venta
id_usuario	BIGINT	FK → usuarios.id_usuario, NN, SIST	Usuario que registró la venta
id_caja	BIGINT	FK → cajas.id_caja, NN, SIST	Caja abierta asociada
creado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que creó el registro
creado_el	TIMESTAMPTZ	NN, DEFAULT now()	Fecha y hora de creación
modificado_por	BIGINT	FK → usuarios.id_usuario, NULL	Usuario que modificó el registro
modificado_el	TIMESTAMPTZ	NULL	Última modificación

2.14  detalle_venta
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_detalle_venta	BIGINT	PK, AUTO	Identificador de línea
cantidad	INTEGER	NN, USR	Cantidad > 0
precio_unitario	NUMERIC(10,2)	NN, SIST	Precio capturado al vender
subtotal	NUMERIC(12,2)	NN, CALC	Cantidad × precio_unitario
id_venta	BIGINT	FK → ventas.id_venta, NN, SIST	Venta
id_producto	BIGINT	FK → productos.id_producto, NN, USR	Producto seleccionado

2.15  venta_pago
Campo	Tipo PostgreSQL	Restricción / origen	Descripción
id_venta_pago	BIGINT	PK, AUTO	Identificador del pago de la venta
importe	NUMERIC(12,2)	NN, USR	Importe imputado a venta > 0
verificado	BOOLEAN	NN, USR/SIST	Confirmación manual para QR/transferencia; propuesta
id_venta	BIGINT	FK → ventas.id_venta, NN, SIST	Venta
id_metodo_pago	BIGINT	FK → metodos_pago.id_metodo_pago, NN, USR	Método elegido

3. Relaciones principales
•	personas (1) — (0..1) usuarios, mediante usuarios.id_persona (UNIQUE, NOT NULL).
•	roles (1) — (N) usuarios, mediante usuarios.id_rol (NOT NULL).
•	roles (M) — (N) permisos, mediante rol_permiso.
•	usuarios (M) — (N) sucursales, mediante usuario_sucursal.
•	categorias (1) — (N) productos.
•	productos (M) — (N) sucursales, mediante producto_sucursal.
•	sucursales (1) — (N) ventas; usuarios (1) — (N) ventas.
•	sucursales (1) — (N) cajas; una sucursal puede tener múltiples registros históricos, pero como máximo una caja con estado ABIERTA.
•	cajas (1) — (N) ventas.
•	ventas (M) — (N) productos, mediante detalle_venta (clase asociativa con cantidad, precio_unitario y subtotal).
•	ventas (M) — (N) metodos_pago, mediante venta_pago (clase asociativa con importe y verificado).
4. Reglas para implementación
•	Para evitar referencias circulares durante las migraciones, crear primero las tablas principales y luego agregar las FK de auditoría hacia usuarios en migraciones posteriores.
•	Las operaciones de venta y descuento de stock deben ejecutarse en una única transacción; no permitir stock negativo.
•	La suma de venta_pago.importe debe coincidir exactamente con ventas.total. Una venta puede combinar efectivo, QR y transferencia bancaria. La conciliación de caja física solo suma pagos en efectivo.
•	El stock pertenece a producto_sucursal, no a productos, porque cada sucursal tiene existencias independientes.
•	La baja de registros administrativos se implementa desactivando (activo = false), no mediante eliminación física ni lógica. Los registros desactivados pueden reactivarse cuando las reglas de negocio lo permitan.
•	usuario_sucursal conserva la clave primaria compuesta (id_usuario, id_sucursal) y no implementa historial de múltiples asignaciones del mismo par. fecha_asignacion utiliza DEFAULT CURRENT_DATE.
•	Una sucursal podrá tener como máximo una caja abierta simultáneamente. Esta regla se implementará en PostgreSQL mediante un índice único parcial sobre id_sucursal condicionado a estado = 'ABIERTA'. Los únicos estados permitidos para cajas son ABIERTA y CERRADA.
•	El efectivo esperado se calcula como monto_inicial + pagos en efectivo, y la diferencia como efectivo_contado − efectivo_esperado. Los pagos QR y transferencia bancaria no aumentan el efectivo esperado. Ambos valores se calculan y muestran después de guardar el cierre.
•	La recuperación automática de contraseñas por correo electrónico está fuera del alcance. El restablecimiento será realizado por un administrador autorizado; usuarios utiliza nombre_usuario y password_hash.
•	La contraseña ingresada debe tener entre 8 y 50 caracteres. Este límite se aplica a la contraseña original, no al hash almacenado en password_hash.
•	Las contraseñas se almacenan exclusivamente como hashes generados mediante Laravel Hash; nunca se almacenan en texto plano.
•	Cada usuario posee exactamente un rol; la autorización comprueba el rol activo y los permisos asociados a ese rol. Un rol con usuarios asignados no puede desactivarse.
•	Toda cuenta de usuario debe tener una persona asociada. Una persona puede existir sin cuenta, pero no puede estar vinculada a más de un usuario.
