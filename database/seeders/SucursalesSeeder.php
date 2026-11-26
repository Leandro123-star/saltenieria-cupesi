<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SucursalesSeeder extends Seeder
{
    private const SUCURSALES = [
        ['Sucursal Centro', 'Villa Montes - Av. Méndez Arcos esq. Ingavi', '46722101', true],
        ['Sucursal Norte', 'Villa Montes - Barrio San Antonio, calle Tarija #45', '46722102', true],
        ['Sucursal Sur', 'Villa Montes - Av. Ingavi frente al mercado municipal', '46722103', true],
        ['Sucursal Terminal', 'Villa Montes - Terminal de buses, local 12', '46722104', false],
    ];

    /** usuario => [sucursales activas], [sucursales finalizadas] */
    private const ASIGNACIONES = [
        'admin' => [['Sucursal Centro', 'Sucursal Norte', 'Sucursal Sur', 'Sucursal Terminal'], []],
        'supervisor' => [['Sucursal Centro', 'Sucursal Norte'], []],
        'cajero1' => [['Sucursal Centro'], []],
        'cajero2' => [['Sucursal Centro'], ['Sucursal Terminal']],
        'cajero3' => [['Sucursal Norte'], []],
        'cajero4' => [['Sucursal Sur'], []],
        'cajero5' => [[], ['Sucursal Norte']],
        'practicante' => [['Sucursal Centro'], []],
    ];

    public function run(): void
    {
        $idAdmin = DB::table('usuarios')->where('nombre_usuario', 'admin')->value('id_usuario');
        $ahora = now();

        foreach (self::SUCURSALES as [$nombre, $direccion, $telefono, $activo]) {
            DB::table('sucursales')->insert([
                'nombre' => $nombre,
                'direccion' => $direccion,
                'telefono' => $telefono,
                'activo' => $activo,
                'creado_por' => $idAdmin,
                'creado_el' => $ahora,
                'modificado_el' => $ahora,
            ]);
        }

        $sucursales = DB::table('sucursales')->pluck('id_sucursal', 'nombre');
        $usuarios = DB::table('usuarios')->pluck('id_usuario', 'nombre_usuario');
        $inicio = today()->subDays(90)->toDateString();

        foreach (self::ASIGNACIONES as $usuario => [$activas, $finalizadas]) {
            foreach ($activas as $sucursal) {
                DB::table('usuario_sucursal')->insert([
                    'id_usuario' => $usuarios[$usuario],
                    'id_sucursal' => $sucursales[$sucursal],
                    'fecha_asignacion' => $inicio,
                    'fecha_fin' => null,
                ]);
            }
            foreach ($finalizadas as $sucursal) {
                DB::table('usuario_sucursal')->insert([
                    'id_usuario' => $usuarios[$usuario],
                    'id_sucursal' => $sucursales[$sucursal],
                    'fecha_asignacion' => $inicio,
                    'fecha_fin' => today()->subDays(45)->toDateString(),
                ]);
            }
        }
    }
}
