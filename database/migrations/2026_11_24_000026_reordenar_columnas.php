<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reordena físicamente las columnas de las tablas para que sigan el orden:
 * PK -> atributos propios -> FK -> auditoría (creado_por, creado_el,
 * modificado_por, modificado_el).
 *
 * PostgreSQL no permite reordenar columnas con ALTER TABLE, así que cada
 * tabla afectada se reconstruye dentro de una transacción: se copian los
 * datos con una lista explícita de columnas, se reemplaza la tabla y se
 * recrean PK, UNIQUE, CHECK, índices y claves foráneas con sus nombres
 * originales. Las tablas que ya tienen el orden esperado no se tocan.
 */
return new class extends Migration
{
    /**
     * Orden objetivo (up).
     *
     * @var array<string, list<string>>
     */
    private const TARGET = [
        'personas' => [
            'id_persona', 'nombres', 'apellidos', 'ci', 'telefono', 'activo',
            'creado_por', 'creado_el', 'modificado_por', 'modificado_el',
        ],
        'usuarios' => [
            'id_usuario', 'nombre_usuario', 'password_hash', 'activo', 'id_persona', 'id_rol',
            'creado_por', 'creado_el', 'modificado_por', 'modificado_el',
        ],
        'roles' => [
            'id_rol', 'nombre', 'descripcion', 'activo',
            'creado_por', 'creado_el', 'modificado_por', 'modificado_el',
        ],
        'permisos' => [
            'id_permiso', 'nombre', 'descripcion',
            'creado_por', 'creado_el', 'modificado_por', 'modificado_el',
        ],
        'sucursales' => [
            'id_sucursal', 'nombre', 'direccion', 'telefono', 'activo',
            'creado_por', 'creado_el', 'modificado_por', 'modificado_el',
        ],
        'categorias' => [
            'id_categoria', 'nombre', 'descripcion', 'activo',
            'creado_por', 'creado_el', 'modificado_por', 'modificado_el',
        ],
        'productos' => [
            'id_producto', 'nombre', 'descripcion', 'foto', 'precio', 'activo', 'id_categoria',
            'creado_por', 'creado_el', 'modificado_por', 'modificado_el',
        ],
        'metodos_pago' => [
            'id_metodo_pago', 'nombre', 'activo',
            'creado_por', 'creado_el', 'modificado_por', 'modificado_el',
        ],
        'cajas' => [
            'id_caja', 'fecha_apertura', 'fecha_cierre', 'monto_inicial',
            'efectivo_contado', 'efectivo_esperado', 'diferencia', 'qr_verificado',
            'qr_esperado', 'diferencia_qr', 'diferencia_total', 'estado',
            'id_sucursal', 'id_usuario_apertura', 'id_usuario_cierre',
            'creado_por', 'creado_el', 'modificado_por', 'modificado_el',
        ],
        'ventas' => [
            'id_venta', 'fecha_venta', 'total', 'estado',
            'id_sucursal', 'id_usuario', 'id_caja',
            'creado_por', 'creado_el', 'modificado_por', 'modificado_el',
        ],
        'usuario_sucursal' => [
            'id_usuario', 'id_sucursal', 'fecha_asignacion', 'fecha_fin',
        ],
        'producto_sucursal' => [
            'id_producto', 'id_sucursal', 'stock_actual', 'stock_minimo',
        ],
    ];

    /**
     * Orden anterior (down), para revertir el cambio.
     *
     * @var array<string, list<string>>
     */
    private const LEGACY = [
        'personas' => [
            'id_persona', 'nombres', 'apellidos', 'ci', 'telefono', 'activo',
            'creado_el', 'modificado_el', 'creado_por', 'modificado_por',
        ],
        'usuarios' => [
            'id_usuario', 'nombre_usuario', 'password_hash', 'activo', 'id_persona', 'id_rol',
            'creado_el', 'modificado_el', 'creado_por', 'modificado_por',
        ],
        'roles' => [
            'id_rol', 'nombre', 'descripcion', 'activo',
            'creado_el', 'modificado_el', 'creado_por', 'modificado_por',
        ],
        'permisos' => [
            'id_permiso', 'nombre', 'descripcion',
            'creado_el', 'modificado_el', 'creado_por', 'modificado_por',
        ],
        'sucursales' => [
            'id_sucursal', 'nombre', 'direccion', 'telefono', 'activo',
            'creado_el', 'modificado_el', 'creado_por', 'modificado_por',
        ],
        'categorias' => [
            'id_categoria', 'nombre', 'descripcion', 'activo',
            'creado_el', 'modificado_el', 'creado_por', 'modificado_por',
        ],
        'productos' => [
            'id_producto', 'nombre', 'descripcion', 'foto', 'precio', 'activo', 'id_categoria',
            'creado_el', 'modificado_el', 'creado_por', 'modificado_por',
        ],
        'metodos_pago' => [
            'id_metodo_pago', 'nombre', 'activo',
            'creado_el', 'modificado_el', 'creado_por', 'modificado_por',
        ],
        'cajas' => [
            'id_caja', 'fecha_apertura', 'fecha_cierre', 'monto_inicial',
            'efectivo_contado', 'efectivo_esperado', 'diferencia', 'qr_verificado',
            'qr_esperado', 'diferencia_qr', 'diferencia_total', 'estado',
            'id_sucursal', 'id_usuario_apertura', 'id_usuario_cierre',
            'creado_el', 'modificado_el', 'creado_por', 'modificado_por',
        ],
        'ventas' => [
            'id_venta', 'fecha_venta', 'total', 'estado',
            'id_sucursal', 'id_usuario', 'id_caja',
            'creado_el', 'modificado_el', 'creado_por', 'modificado_por',
        ],
        'usuario_sucursal' => [
            'fecha_asignacion', 'fecha_fin', 'id_usuario', 'id_sucursal',
        ],
        'producto_sucursal' => [
            'stock_actual', 'stock_minimo', 'id_producto', 'id_sucursal',
        ],
    ];

    /**
     * Definición completa de cada tabla reconstruible.
     *
     * @var array<string, array{
     *     columns: array<string, string>,
     *     sequence: array{string, string}|null,
     *     primary: array{string, list<string>},
     *     uniques: list<array{string, list<string>}>,
     *     checks: list<array{string, string}>,
     *     indexes: list<string>
     * }>
     */
    private const SCHEMA = [
        'personas' => [
            'columns' => [
                'id_persona' => 'BIGSERIAL',
                'nombres' => 'VARCHAR(30) NOT NULL',
                'apellidos' => 'VARCHAR(30) NOT NULL',
                'ci' => 'VARCHAR(20)',
                'telefono' => 'VARCHAR(15)',
                'activo' => 'BOOLEAN NOT NULL DEFAULT true',
                'creado_por' => 'BIGINT',
                'creado_el' => 'TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'modificado_por' => 'BIGINT',
                'modificado_el' => 'TIMESTAMPTZ',
            ],
            'sequence' => ['personas_id_persona_seq', 'id_persona'],
            'primary' => ['personas_pkey', ['id_persona']],
            'uniques' => [['personas_ci_unique', ['ci']]],
            'checks' => [],
            'indexes' => [],
        ],
        'usuarios' => [
            'columns' => [
                'id_usuario' => 'BIGSERIAL',
                'nombre_usuario' => 'VARCHAR(30) NOT NULL',
                'password_hash' => 'VARCHAR(255) NOT NULL',
                'activo' => 'BOOLEAN NOT NULL',
                'id_persona' => 'BIGINT NOT NULL',
                'id_rol' => 'BIGINT NOT NULL',
                'creado_por' => 'BIGINT',
                'creado_el' => 'TIMESTAMPTZ NOT NULL DEFAULT now()',
                'modificado_por' => 'BIGINT',
                'modificado_el' => 'TIMESTAMPTZ',
            ],
            'sequence' => ['usuarios_id_usuario_seq', 'id_usuario'],
            'primary' => ['usuarios_pkey', ['id_usuario']],
            'uniques' => [
                ['usuarios_nombre_usuario_unique', ['nombre_usuario']],
                ['usuarios_id_persona_unique', ['id_persona']],
            ],
            'checks' => [],
            'indexes' => [],
        ],
        'roles' => [
            'columns' => [
                'id_rol' => 'BIGSERIAL',
                'nombre' => 'VARCHAR(30) NOT NULL',
                'descripcion' => 'VARCHAR(100)',
                'activo' => 'BOOLEAN NOT NULL DEFAULT true',
                'creado_por' => 'BIGINT',
                'creado_el' => 'TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'modificado_por' => 'BIGINT',
                'modificado_el' => 'TIMESTAMPTZ',
            ],
            'sequence' => ['roles_id_rol_seq', 'id_rol'],
            'primary' => ['roles_pkey', ['id_rol']],
            'uniques' => [['roles_nombre_unique', ['nombre']]],
            'checks' => [],
            'indexes' => [],
        ],
        'permisos' => [
            'columns' => [
                'id_permiso' => 'BIGSERIAL',
                'nombre' => 'VARCHAR(30) NOT NULL',
                'descripcion' => 'VARCHAR(100)',
                'creado_por' => 'BIGINT',
                'creado_el' => 'TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'modificado_por' => 'BIGINT',
                'modificado_el' => 'TIMESTAMPTZ',
            ],
            'sequence' => ['permisos_id_permiso_seq', 'id_permiso'],
            'primary' => ['permisos_pkey', ['id_permiso']],
            'uniques' => [['permisos_nombre_unique', ['nombre']]],
            'checks' => [],
            'indexes' => [],
        ],
        'sucursales' => [
            'columns' => [
                'id_sucursal' => 'BIGSERIAL',
                'nombre' => 'VARCHAR(50) NOT NULL',
                'direccion' => 'VARCHAR(150) NOT NULL',
                'telefono' => 'VARCHAR(15)',
                'activo' => 'BOOLEAN NOT NULL',
                'creado_por' => 'BIGINT',
                'creado_el' => 'TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'modificado_por' => 'BIGINT',
                'modificado_el' => 'TIMESTAMPTZ',
            ],
            'sequence' => ['sucursales_id_sucursal_seq', 'id_sucursal'],
            'primary' => ['sucursales_pkey', ['id_sucursal']],
            'uniques' => [['sucursales_nombre_unique', ['nombre']]],
            'checks' => [],
            'indexes' => [],
        ],
        'categorias' => [
            'columns' => [
                'id_categoria' => 'BIGSERIAL',
                'nombre' => 'VARCHAR(30) NOT NULL',
                'descripcion' => 'VARCHAR(100)',
                'activo' => 'BOOLEAN NOT NULL',
                'creado_por' => 'BIGINT',
                'creado_el' => 'TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'modificado_por' => 'BIGINT',
                'modificado_el' => 'TIMESTAMPTZ',
            ],
            'sequence' => ['categorias_id_categoria_seq', 'id_categoria'],
            'primary' => ['categorias_pkey', ['id_categoria']],
            'uniques' => [['categorias_nombre_unique', ['nombre']]],
            'checks' => [],
            'indexes' => [],
        ],
        'productos' => [
            'columns' => [
                'id_producto' => 'BIGSERIAL',
                'nombre' => 'VARCHAR(50) NOT NULL',
                'descripcion' => 'VARCHAR(100)',
                'foto' => 'VARCHAR(255)',
                'precio' => 'NUMERIC(10,2) NOT NULL',
                'activo' => 'BOOLEAN NOT NULL',
                'id_categoria' => 'BIGINT NOT NULL',
                'creado_por' => 'BIGINT',
                'creado_el' => 'TIMESTAMPTZ NOT NULL DEFAULT now()',
                'modificado_por' => 'BIGINT',
                'modificado_el' => 'TIMESTAMPTZ',
            ],
            'sequence' => ['productos_id_producto_seq', 'id_producto'],
            'primary' => ['productos_pkey', ['id_producto']],
            'uniques' => [],
            'checks' => [['productos_precio_positivo', 'precio > 0']],
            'indexes' => [
                'CREATE INDEX productos_categoria_index ON {table} (id_categoria)',
            ],
        ],
        'metodos_pago' => [
            'columns' => [
                'id_metodo_pago' => 'BIGSERIAL',
                'nombre' => 'VARCHAR(30) NOT NULL',
                'activo' => 'BOOLEAN NOT NULL',
                'creado_por' => 'BIGINT',
                'creado_el' => 'TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'modificado_por' => 'BIGINT',
                'modificado_el' => 'TIMESTAMPTZ',
            ],
            'sequence' => ['metodos_pago_id_metodo_pago_seq', 'id_metodo_pago'],
            'primary' => ['metodos_pago_pkey', ['id_metodo_pago']],
            'uniques' => [['metodos_pago_nombre_unique', ['nombre']]],
            'checks' => [],
            'indexes' => [],
        ],
        'cajas' => [
            'columns' => [
                'id_caja' => 'BIGSERIAL',
                'fecha_apertura' => 'TIMESTAMPTZ NOT NULL DEFAULT now()',
                'fecha_cierre' => 'TIMESTAMPTZ',
                'monto_inicial' => 'NUMERIC(12,2) NOT NULL',
                'efectivo_contado' => 'NUMERIC(12,2)',
                'efectivo_esperado' => 'NUMERIC(12,2)',
                'diferencia' => 'NUMERIC(12,2)',
                'qr_verificado' => 'NUMERIC(12,2)',
                'qr_esperado' => 'NUMERIC(12,2)',
                'diferencia_qr' => 'NUMERIC(12,2)',
                'diferencia_total' => 'NUMERIC(12,2)',
                'estado' => 'VARCHAR(15) NOT NULL',
                'id_sucursal' => 'BIGINT NOT NULL',
                'id_usuario_apertura' => 'BIGINT NOT NULL',
                'id_usuario_cierre' => 'BIGINT',
                'creado_por' => 'BIGINT',
                'creado_el' => 'TIMESTAMPTZ NOT NULL DEFAULT now()',
                'modificado_por' => 'BIGINT',
                'modificado_el' => 'TIMESTAMPTZ',
            ],
            'sequence' => ['cajas_id_caja_seq', 'id_caja'],
            'primary' => ['cajas_pkey', ['id_caja']],
            'uniques' => [],
            'checks' => [
                ['cajas_efectivo_contado_no_negativo', 'efectivo_contado IS NULL OR efectivo_contado >= 0'],
                ['cajas_estado_valido', "estado IN ('ABIERTA', 'CERRADA')"],
                ['cajas_monto_inicial_no_negativo', 'monto_inicial >= 0'],
                ['cajas_qr_esperado_no_negativo', 'qr_esperado IS NULL OR qr_esperado >= 0'],
                ['cajas_qr_verificado_no_negativo', 'qr_verificado IS NULL OR qr_verificado >= 0'],
            ],
            'indexes' => [
                'CREATE INDEX cajas_sucursal_apertura_index ON {table} (id_sucursal, fecha_apertura)',
                "CREATE UNIQUE INDEX cajas_una_abierta_por_sucursal ON {table} (id_sucursal) WHERE estado = 'ABIERTA'",
            ],
        ],
        'ventas' => [
            'columns' => [
                'id_venta' => 'BIGSERIAL',
                'fecha_venta' => 'TIMESTAMPTZ NOT NULL DEFAULT now()',
                'total' => 'NUMERIC(12,2) NOT NULL',
                'estado' => 'VARCHAR(15) NOT NULL',
                'id_sucursal' => 'BIGINT NOT NULL',
                'id_usuario' => 'BIGINT NOT NULL',
                'id_caja' => 'BIGINT NOT NULL',
                'creado_por' => 'BIGINT',
                'creado_el' => 'TIMESTAMPTZ NOT NULL DEFAULT now()',
                'modificado_por' => 'BIGINT',
                'modificado_el' => 'TIMESTAMPTZ',
            ],
            'sequence' => ['ventas_id_venta_seq', 'id_venta'],
            'primary' => ['ventas_pkey', ['id_venta']],
            'uniques' => [],
            'checks' => [['ventas_total_no_negativo', 'total >= 0']],
            'indexes' => [
                'CREATE INDEX ventas_caja_fecha_index ON {table} (id_caja, fecha_venta)',
                'CREATE INDEX ventas_sucursal_fecha_index ON {table} (id_sucursal, fecha_venta)',
            ],
        ],
        'usuario_sucursal' => [
            'columns' => [
                'id_usuario' => 'BIGINT NOT NULL',
                'id_sucursal' => 'BIGINT NOT NULL',
                'fecha_asignacion' => 'DATE NOT NULL DEFAULT CURRENT_DATE',
                'fecha_fin' => 'DATE',
            ],
            'sequence' => null,
            'primary' => ['usuario_sucursal_pkey', ['id_usuario', 'id_sucursal']],
            'uniques' => [],
            'checks' => [],
            'indexes' => [
                'CREATE INDEX usuario_sucursal_sucursal_index ON {table} (id_sucursal)',
            ],
        ],
        'producto_sucursal' => [
            'columns' => [
                'id_producto' => 'BIGINT NOT NULL',
                'id_sucursal' => 'BIGINT NOT NULL',
                'stock_actual' => 'INTEGER NOT NULL',
                'stock_minimo' => 'INTEGER NOT NULL',
            ],
            'sequence' => null,
            'primary' => ['producto_sucursal_pkey', ['id_producto', 'id_sucursal']],
            'uniques' => [],
            'checks' => [
                ['producto_sucursal_stock_actual_no_negativo', 'stock_actual >= 0'],
                ['producto_sucursal_stock_minimo_no_negativo', 'stock_minimo >= 0'],
            ],
            'indexes' => [
                'CREATE INDEX producto_sucursal_sucursal_index ON {table} (id_sucursal)',
            ],
        ],
    ];

    public function up(): void
    {
        $this->apply(self::TARGET);
    }

    public function down(): void
    {
        $this->apply(self::LEGACY);
    }

    /**
     * @param  array<string, list<string>>  $orders
     */
    private function apply(array $orders): void
    {
        $pending = [];

        foreach ($orders as $table => $columns) {
            if ($this->currentColumns($table) !== $columns) {
                $pending[$table] = $columns;
            }
        }

        if ($pending === []) {
            return;
        }

        DB::transaction(function () use ($pending): void {
            $foreignKeys = $this->snapshotForeignKeys();

            $this->dropForeignKeys($foreignKeys);

            foreach ($pending as $table => $order) {
                $this->rebuild($table, $order);
            }

            $this->recreateForeignKeys($foreignKeys);
        });
    }

    /**
     * @return list<string>
     */
    private function currentColumns(string $table): array
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', $table)
            ->orderBy('ordinal_position')
            ->pluck('column_name')
            ->all();
    }

    /**
     * @return list<object{table_name: string, name: string, definition: string}>
     */
    private function snapshotForeignKeys(): array
    {
        return DB::select(<<<'SQL'
            SELECT conrelid::regclass::text AS table_name,
                   conname AS name,
                   pg_get_constraintdef(oid) AS definition
            FROM pg_constraint
            WHERE contype = 'f'
              AND connamespace = 'public'::regnamespace
            ORDER BY conrelid::regclass::text, conname
        SQL);
    }

    /**
     * @param  list<object{table_name: string, name: string, definition: string}>  $foreignKeys
     */
    private function dropForeignKeys(array $foreignKeys): void
    {
        foreach ($foreignKeys as $foreignKey) {
            DB::statement("ALTER TABLE {$foreignKey->table_name} DROP CONSTRAINT {$foreignKey->name}");
        }
    }

    /**
     * @param  list<object{table_name: string, name: string, definition: string}>  $foreignKeys
     */
    private function recreateForeignKeys(array $foreignKeys): void
    {
        foreach ($foreignKeys as $foreignKey) {
            DB::statement(
                "ALTER TABLE {$foreignKey->table_name} ADD CONSTRAINT {$foreignKey->name} {$foreignKey->definition}"
            );
        }
    }

    /**
     * @param  list<string>  $order
     */
    private function rebuild(string $table, array $order): void
    {
        $schema = self::SCHEMA[$table];
        $temporary = $table.'_tmp';

        $definitions = [];
        foreach ($order as $column) {
            $definitions[] = $column.' '.$schema['columns'][$column];
        }

        DB::statement("CREATE TABLE {$temporary} (\n    ".implode(",\n    ", $definitions)."\n)");

        $columnList = implode(', ', $order);
        DB::statement("INSERT INTO {$temporary} ({$columnList}) SELECT {$columnList} FROM {$table}");

        DB::statement("DROP TABLE {$table}");
        DB::statement("ALTER TABLE {$temporary} RENAME TO {$table}");

        if ($schema['sequence'] !== null) {
            [$sequence, $sequenceColumn] = $schema['sequence'];

            DB::statement("ALTER SEQUENCE {$temporary}_{$sequenceColumn}_seq RENAME TO {$sequence}");
            DB::statement(
                "SELECT setval('{$sequence}', COALESCE(MAX({$sequenceColumn}), 1), MAX({$sequenceColumn}) IS NOT NULL) FROM {$table}"
            );
        }

        [$primaryName, $primaryColumns] = $schema['primary'];
        DB::statement(
            "ALTER TABLE {$table} ADD CONSTRAINT {$primaryName} PRIMARY KEY (".implode(', ', $primaryColumns).')'
        );

        foreach ($schema['uniques'] as [$name, $columns]) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} UNIQUE (".implode(', ', $columns).')');
        }

        foreach ($schema['checks'] as [$name, $expression]) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
        }

        foreach ($schema['indexes'] as $sql) {
            DB::statement(str_replace('{table}', $table, $sql));
        }
    }
};
