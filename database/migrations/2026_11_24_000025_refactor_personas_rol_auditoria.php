<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->renamePersonas();
            $this->moveRolToUsuarios();
            $this->dropDeletedColumns();
            $this->rebuildUsuarios();
            $this->rebuildProductos();
            $this->rebuildCajas();
            $this->rebuildVentas();
            $this->recreateForeignKeys();
            $this->renamePersonaPermission();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            Schema::create('usuario_rol', function (Blueprint $table): void {
                $table->unsignedBigInteger('id_usuario');
                $table->unsignedBigInteger('id_rol');
                $table->primary(['id_usuario', 'id_rol']);
                $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->cascadeOnDelete();
                $table->foreign('id_rol')->references('id_rol')->on('roles')->cascadeOnDelete();
            });

            DB::statement('INSERT INTO usuario_rol (id_usuario, id_rol) SELECT id_usuario, id_rol FROM usuarios WHERE id_rol IS NOT NULL');

            Schema::table('usuarios', function (Blueprint $table): void {
                $table->dropForeign(['id_rol']);
                $table->dropColumn('id_rol');
            });

            Schema::table('usuarios', function (Blueprint $table): void {
                $table->renameColumn('id_persona', 'id_personal');
            });

            DB::statement('ALTER TABLE usuarios ALTER COLUMN id_personal DROP NOT NULL');

            Schema::table('personas', function (Blueprint $table): void {
                $table->renameColumn('id_persona', 'id_personal');
            });

            Schema::rename('personas', 'personal');

            DB::statement('ALTER SEQUENCE IF EXISTS personas_id_persona_seq RENAME TO personal_id_personal_seq');
            DB::statement('ALTER INDEX IF EXISTS personas_ci_unique RENAME TO personal_ci_unique');
            DB::statement('ALTER TABLE personal RENAME CONSTRAINT personas_pkey TO personal_pkey');

            $tables = ['personal', 'usuarios', 'roles', 'permisos', 'sucursales', 'categorias', 'productos', 'metodos_pago'];

            foreach ($tables as $table) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->timestampTz('eliminado_el')->nullable();
                    $table->unsignedBigInteger('eliminado_por')->nullable();
                });
            }

            foreach ($tables as $table) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->foreign('eliminado_por')->references('id_usuario')->on('usuarios');
                });
            }

            DB::table('permisos')->where('nombre', 'gestionar_personas')->update(['nombre' => 'gestionar_personal']);
        });
    }

    private function renamePersonas(): void
    {
        Schema::rename('personal', 'personas');

        Schema::table('personas', function (Blueprint $table): void {
            $table->renameColumn('id_personal', 'id_persona');
        });

        DB::statement('ALTER SEQUENCE IF EXISTS personal_id_personal_seq RENAME TO personas_id_persona_seq');
        DB::statement('ALTER INDEX IF EXISTS personal_ci_unique RENAME TO personas_ci_unique');
        DB::statement('ALTER TABLE personas RENAME CONSTRAINT personal_pkey TO personas_pkey');

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->renameColumn('id_personal', 'id_persona');
        });
    }

    private function moveRolToUsuarios(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_rol')->nullable();
        });

        DB::statement('UPDATE usuarios u SET id_rol = (SELECT MIN(ur.id_rol) FROM usuario_rol ur WHERE ur.id_usuario = u.id_usuario)');

        $sinRol = DB::table('usuarios')->whereNull('id_rol')->count();

        if ($sinRol > 0) {
            throw new RuntimeException("Hay {$sinRol} usuarios sin rol. Asigne un rol antes de ejecutar esta migración.");
        }

        DB::statement('ALTER TABLE usuarios ALTER COLUMN id_rol SET NOT NULL');
        DB::statement('ALTER TABLE usuarios ALTER COLUMN id_persona SET NOT NULL');

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->foreign('id_rol')->references('id_rol')->on('roles')->restrictOnDelete();
        });

        Schema::dropIfExists('usuario_rol');
    }

    private function dropDeletedColumns(): void
    {
        foreach (['personas', 'usuarios', 'roles', 'permisos', 'sucursales', 'categorias', 'productos', 'metodos_pago'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn(['eliminado_el', 'eliminado_por']);
            });
        }
    }

    private function rebuildUsuarios(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE usuarios_tmp (
                id_usuario BIGSERIAL PRIMARY KEY,
                nombre_usuario VARCHAR(30) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                activo BOOLEAN NOT NULL,
                id_persona BIGINT NOT NULL UNIQUE,
                id_rol BIGINT NOT NULL,
                creado_el TIMESTAMPTZ NOT NULL DEFAULT now(),
                modificado_el TIMESTAMPTZ NULL,
                creado_por BIGINT NULL,
                modificado_por BIGINT NULL
            )
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO usuarios_tmp (id_usuario, nombre_usuario, password_hash, activo, id_persona, id_rol, creado_el, modificado_el, creado_por, modificado_por)
            SELECT id_usuario, nombre_usuario, password_hash, activo, id_persona, id_rol, creado_el, modificado_el, creado_por, modificado_por
            FROM usuarios
        SQL);

        DB::statement('DROP TABLE usuarios CASCADE');
        DB::statement('ALTER TABLE usuarios_tmp RENAME TO usuarios');
        DB::statement('ALTER SEQUENCE usuarios_tmp_id_usuario_seq RENAME TO usuarios_id_usuario_seq');
        DB::statement('ALTER TABLE usuarios RENAME CONSTRAINT usuarios_tmp_pkey TO usuarios_pkey');
        DB::statement('ALTER TABLE usuarios RENAME CONSTRAINT usuarios_tmp_nombre_usuario_key TO usuarios_nombre_usuario_unique');
        DB::statement('ALTER TABLE usuarios RENAME CONSTRAINT usuarios_tmp_id_persona_key TO usuarios_id_persona_unique');
        DB::statement("SELECT setval('usuarios_id_usuario_seq', COALESCE((SELECT MAX(id_usuario) FROM usuarios), 1))");
    }

    private function rebuildProductos(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE productos_tmp (
                id_producto BIGSERIAL PRIMARY KEY,
                nombre VARCHAR(50) NOT NULL,
                descripcion VARCHAR(100) NULL,
                foto VARCHAR(255) NULL,
                precio NUMERIC(10,2) NOT NULL,
                activo BOOLEAN NOT NULL,
                id_categoria BIGINT NOT NULL,
                creado_el TIMESTAMPTZ NOT NULL DEFAULT now(),
                modificado_el TIMESTAMPTZ NULL,
                creado_por BIGINT NULL,
                modificado_por BIGINT NULL
            )
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO productos_tmp (id_producto, nombre, descripcion, foto, precio, activo, id_categoria, creado_el, modificado_el, creado_por, modificado_por)
            SELECT id_producto, nombre, descripcion, foto, precio, activo, id_categoria, creado_el, modificado_el, creado_por, modificado_por
            FROM productos
        SQL);

        DB::statement('DROP TABLE productos CASCADE');
        DB::statement('ALTER TABLE productos_tmp RENAME TO productos');
        DB::statement('ALTER SEQUENCE productos_tmp_id_producto_seq RENAME TO productos_id_producto_seq');
        DB::statement('ALTER TABLE productos RENAME CONSTRAINT productos_tmp_pkey TO productos_pkey');
        DB::statement("SELECT setval('productos_id_producto_seq', COALESCE((SELECT MAX(id_producto) FROM productos), 1))");
        DB::statement('CREATE INDEX productos_categoria_index ON productos (id_categoria)');
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_precio_positivo CHECK (precio > 0)');
    }

    private function rebuildCajas(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE cajas_tmp (
                id_caja BIGSERIAL PRIMARY KEY,
                fecha_apertura TIMESTAMPTZ NOT NULL DEFAULT now(),
                fecha_cierre TIMESTAMPTZ NULL,
                monto_inicial NUMERIC(12,2) NOT NULL,
                efectivo_contado NUMERIC(12,2) NULL,
                efectivo_esperado NUMERIC(12,2) NULL,
                diferencia NUMERIC(12,2) NULL,
                qr_verificado NUMERIC(12,2) NULL,
                qr_esperado NUMERIC(12,2) NULL,
                diferencia_qr NUMERIC(12,2) NULL,
                diferencia_total NUMERIC(12,2) NULL,
                estado VARCHAR(15) NOT NULL,
                id_sucursal BIGINT NOT NULL,
                id_usuario_apertura BIGINT NOT NULL,
                id_usuario_cierre BIGINT NULL,
                creado_el TIMESTAMPTZ NOT NULL DEFAULT now(),
                modificado_el TIMESTAMPTZ NULL,
                creado_por BIGINT NULL,
                modificado_por BIGINT NULL
            )
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO cajas_tmp (id_caja, fecha_apertura, fecha_cierre, monto_inicial, efectivo_contado, efectivo_esperado, diferencia, qr_verificado, qr_esperado, diferencia_qr, diferencia_total, estado, id_sucursal, id_usuario_apertura, id_usuario_cierre, creado_el, modificado_el, creado_por, modificado_por)
            SELECT id_caja, fecha_apertura, fecha_cierre, monto_inicial, efectivo_contado, efectivo_esperado, diferencia, qr_verificado, qr_esperado, diferencia_qr, diferencia_total, estado, id_sucursal, id_usuario_apertura, id_usuario_cierre, creado_el, modificado_el, creado_por, modificado_por
            FROM cajas
        SQL);

        DB::statement('DROP TABLE cajas CASCADE');
        DB::statement('ALTER TABLE cajas_tmp RENAME TO cajas');
        DB::statement('ALTER SEQUENCE cajas_tmp_id_caja_seq RENAME TO cajas_id_caja_seq');
        DB::statement('ALTER TABLE cajas RENAME CONSTRAINT cajas_tmp_pkey TO cajas_pkey');
        DB::statement("SELECT setval('cajas_id_caja_seq', COALESCE((SELECT MAX(id_caja) FROM cajas), 1))");
        DB::statement('CREATE INDEX cajas_sucursal_apertura_index ON cajas (id_sucursal, fecha_apertura)');
        DB::statement("CREATE UNIQUE INDEX cajas_una_abierta_por_sucursal ON cajas (id_sucursal) WHERE estado = 'ABIERTA'");
        DB::statement('ALTER TABLE cajas ADD CONSTRAINT cajas_monto_inicial_no_negativo CHECK (monto_inicial >= 0)');
        DB::statement('ALTER TABLE cajas ADD CONSTRAINT cajas_efectivo_contado_no_negativo CHECK (efectivo_contado IS NULL OR efectivo_contado >= 0)');
        DB::statement("ALTER TABLE cajas ADD CONSTRAINT cajas_estado_valido CHECK (estado IN ('ABIERTA', 'CERRADA'))");
        DB::statement('ALTER TABLE cajas ADD CONSTRAINT cajas_qr_verificado_no_negativo CHECK (qr_verificado IS NULL OR qr_verificado >= 0)');
        DB::statement('ALTER TABLE cajas ADD CONSTRAINT cajas_qr_esperado_no_negativo CHECK (qr_esperado IS NULL OR qr_esperado >= 0)');
    }

    private function rebuildVentas(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE ventas_tmp (
                id_venta BIGSERIAL PRIMARY KEY,
                fecha_venta TIMESTAMPTZ NOT NULL DEFAULT now(),
                total NUMERIC(12,2) NOT NULL,
                estado VARCHAR(15) NOT NULL,
                id_sucursal BIGINT NOT NULL,
                id_usuario BIGINT NOT NULL,
                id_caja BIGINT NOT NULL,
                creado_el TIMESTAMPTZ NOT NULL DEFAULT now(),
                modificado_el TIMESTAMPTZ NULL,
                creado_por BIGINT NULL,
                modificado_por BIGINT NULL
            )
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO ventas_tmp (id_venta, fecha_venta, total, estado, id_sucursal, id_usuario, id_caja, creado_el, modificado_el, creado_por, modificado_por)
            SELECT id_venta, fecha_venta, total, estado, id_sucursal, id_usuario, id_caja, creado_el, modificado_el, creado_por, modificado_por
            FROM ventas
        SQL);

        DB::statement('DROP TABLE ventas CASCADE');
        DB::statement('ALTER TABLE ventas_tmp RENAME TO ventas');
        DB::statement('ALTER SEQUENCE ventas_tmp_id_venta_seq RENAME TO ventas_id_venta_seq');
        DB::statement('ALTER TABLE ventas RENAME CONSTRAINT ventas_tmp_pkey TO ventas_pkey');
        DB::statement("SELECT setval('ventas_id_venta_seq', COALESCE((SELECT MAX(id_venta) FROM ventas), 1))");
        DB::statement('CREATE INDEX ventas_sucursal_fecha_index ON ventas (id_sucursal, fecha_venta)');
        DB::statement('CREATE INDEX ventas_caja_fecha_index ON ventas (id_caja, fecha_venta)');
        DB::statement('ALTER TABLE ventas ADD CONSTRAINT ventas_total_no_negativo CHECK (total >= 0)');
    }

    private function recreateForeignKeys(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->foreign('id_persona')->references('id_persona')->on('personas')->restrictOnDelete();
            $table->foreign('id_rol')->references('id_rol')->on('roles')->restrictOnDelete();
            $table->foreign('creado_por')->references('id_usuario')->on('usuarios');
            $table->foreign('modificado_por')->references('id_usuario')->on('usuarios');
        });

        foreach (['personas', 'roles', 'permisos', 'sucursales', 'categorias', 'metodos_pago'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreign('creado_por')->references('id_usuario')->on('usuarios');
                $table->foreign('modificado_por')->references('id_usuario')->on('usuarios');
            });
        }

        Schema::table('productos', function (Blueprint $table): void {
            $table->foreign('id_categoria')->references('id_categoria')->on('categorias');
            $table->foreign('creado_por')->references('id_usuario')->on('usuarios');
            $table->foreign('modificado_por')->references('id_usuario')->on('usuarios');
        });

        Schema::table('cajas', function (Blueprint $table): void {
            $table->foreign('id_sucursal')->references('id_sucursal')->on('sucursales');
            $table->foreign('id_usuario_apertura')->references('id_usuario')->on('usuarios');
            $table->foreign('id_usuario_cierre')->references('id_usuario')->on('usuarios');
            $table->foreign('creado_por')->references('id_usuario')->on('usuarios');
            $table->foreign('modificado_por')->references('id_usuario')->on('usuarios');
        });

        Schema::table('ventas', function (Blueprint $table): void {
            $table->foreign('id_sucursal')->references('id_sucursal')->on('sucursales');
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
            $table->foreign('id_caja')->references('id_caja')->on('cajas');
            $table->foreign('creado_por')->references('id_usuario')->on('usuarios');
            $table->foreign('modificado_por')->references('id_usuario')->on('usuarios');
        });

        Schema::table('usuario_sucursal', function (Blueprint $table): void {
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->cascadeOnDelete();
        });

        Schema::table('producto_sucursal', function (Blueprint $table): void {
            $table->foreign('id_producto')->references('id_producto')->on('productos');
        });

        Schema::table('detalle_venta', function (Blueprint $table): void {
            $table->foreign('id_venta')->references('id_venta')->on('ventas');
            $table->foreign('id_producto')->references('id_producto')->on('productos');
        });

        Schema::table('venta_pago', function (Blueprint $table): void {
            $table->foreign('id_venta')->references('id_venta')->on('ventas');
        });
    }

    private function renamePersonaPermission(): void
    {
        DB::table('permisos')
            ->where('nombre', 'gestionar_personal')
            ->update(['nombre' => 'gestionar_personas']);
    }
};
