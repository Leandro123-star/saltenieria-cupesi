<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeguridadSeeder extends Seeder
{
    /** Claves usadas por authorizePermission() en los controladores. */
    public const PERMISOS = [
        'visualizar_panel' => 'Visualizar el panel principal',
        'registrar_ventas' => 'Registrar ventas y comprobantes',
        'consultar_inventario' => 'Consultar existencias',
        'gestionar_caja' => 'Abrir, consultar y cerrar caja',
        'generar_reportes' => 'Generar y exportar reportes',
        'gestionar_categorias' => 'Gestionar categorías',
        'gestionar_productos' => 'Gestionar productos',
        'gestionar_sucursales' => 'Gestionar sucursales',
        'gestionar_metodos_pago' => 'Gestionar métodos de pago',
        'gestionar_personas' => 'Gestionar personal',
        'gestionar_usuarios' => 'Gestionar usuarios',
        'gestionar_roles' => 'Gestionar roles',
        'gestionar_permisos' => 'Gestionar permisos',
    ];

    public const ROLES = [
        'Administrador' => [
            'descripcion' => 'Acceso completo al sistema',
            'activo' => true,
            'permisos' => '*',
        ],
        'Supervisor' => [
            'descripcion' => 'Supervisión de sucursal, inventario y reportes',
            'activo' => true,
            'permisos' => ['visualizar_panel', 'registrar_ventas', 'consultar_inventario', 'gestionar_caja', 'generar_reportes', 'gestionar_productos', 'gestionar_categorias'],
        ],
        'Cajero' => [
            'descripcion' => 'Ventas, caja e inventario',
            'activo' => true,
            'permisos' => ['visualizar_panel', 'registrar_ventas', 'consultar_inventario', 'gestionar_caja'],
        ],
        'Practicante' => [
            'descripcion' => 'Rol inactivo de ejemplo',
            'activo' => false,
            'permisos' => ['visualizar_panel', 'consultar_inventario'],
        ],
    ];

    public function run(): void
    {
        $ahora = now();

        foreach (self::PERMISOS as $clave => $descripcion) {
            DB::table('permisos')->insert([
                'nombre' => $clave,
                'clave' => $clave,
                'descripcion' => $descripcion,
                'creado_el' => $ahora,
                'modificado_el' => $ahora,
            ]);
        }

        $permisos = DB::table('permisos')->pluck('id_permiso', 'clave');

        foreach (self::ROLES as $nombre => $rol) {
            $idRol = DB::table('roles')->insertGetId([
                'nombre' => $nombre,
                'descripcion' => $rol['descripcion'],
                'activo' => $rol['activo'],
                'creado_el' => $ahora,
                'modificado_el' => $ahora,
            ], 'id_rol');

            $claves = $rol['permisos'] === '*' ? array_keys(self::PERMISOS) : $rol['permisos'];

            DB::table('rol_permiso')->insert(array_map(fn (string $clave) => [
                'id_rol' => $idRol,
                'id_permiso' => $permisos[$clave],
            ], $claves));
        }
    }
}
