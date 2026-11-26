<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoSeeder extends Seeder
{
    private const CATEGORIAS = [
        ['Salteñas', 'Salteñas horneadas al estilo tradicional', true],
        ['Empanadas', 'Empanadas y tucumanas', true],
        ['Bebidas', 'Refrescos, gaseosas y bebidas calientes', true],
        ['Postres', 'Postres y acompañamientos', true],
        ['Combos', 'Promociones de salteña con bebida', true],
        ['Temporada', 'Productos de temporada (inactiva)', false],
    ];

    private const METODOS_PAGO = [
        ['Efectivo', true],
        ['QR', true],
        ['Transferencia', true],
        ['Tarjeta', false],
    ];

    /** [nombre, descripción, precio, categoría, activo] */
    private const PRODUCTOS = [
        ['Salteña de carne', 'Salteña jugosa de carne de res', 8.00, 'Salteñas', true],
        ['Salteña de pollo', 'Salteña jugosa de pollo', 8.00, 'Salteñas', true],
        ['Salteña picante', 'Salteña de carne con ají picante', 8.50, 'Salteñas', true],
        ['Salteña de queso', 'Salteña de queso criollo', 7.00, 'Salteñas', true],
        ['Salteña vegetariana', 'Salteña de verduras y queso', 7.50, 'Salteñas', true],
        ['Salteña de charque', 'Salteña especial de charque', 9.00, 'Salteñas', false],
        ['Empanada de queso', 'Empanada frita de queso', 4.00, 'Empanadas', true],
        ['Empanada de carne', 'Empanada horneada de carne', 5.00, 'Empanadas', true],
        ['Tucumana', 'Tucumana de carne con salsa', 6.00, 'Empanadas', true],
        ['Gaseosa 500 ml', 'Gaseosa personal en botella', 6.00, 'Bebidas', true],
        ['Gaseosa 2 L', 'Gaseosa familiar', 15.00, 'Bebidas', true],
        ['Agua 600 ml', 'Agua sin gas', 4.00, 'Bebidas', true],
        ['Mocochinchi', 'Refresco tradicional de durazno deshidratado', 5.00, 'Bebidas', true],
        ['Jugo de naranja', 'Jugo natural de naranja', 8.00, 'Bebidas', true],
        ['Café', 'Café pasado', 5.00, 'Bebidas', true],
        ['Api con pastel', 'Api morado con pastel de queso', 10.00, 'Postres', true],
        ['Gelatina', 'Gelatina con crema', 4.00, 'Postres', true],
        ['Combo salteña + gaseosa', 'Salteña de carne o pollo con gaseosa 500 ml', 13.00, 'Combos', true],
        ['Combo familiar', '6 salteñas y gaseosa 2 L', 58.00, 'Combos', true],
    ];

    /** Productos que no se ofrecen en la sucursal indicada. */
    private const NO_DISPONIBLES = [
        'Sucursal Sur' => ['Jugo de naranja', 'Api con pastel', 'Combo familiar'],
        'Sucursal Terminal' => ['Api con pastel', 'Gelatina', 'Combo familiar', 'Tucumana'],
    ];

    /** Productos con stock por debajo del mínimo para probar alertas. */
    private const STOCK_BAJO = [
        'Sucursal Centro' => ['Salteña picante', 'Mocochinchi'],
        'Sucursal Norte' => ['Gaseosa 2 L'],
        'Sucursal Sur' => ['Salteña vegetariana', 'Café'],
    ];

    public function run(): void
    {
        mt_srand(511);

        $idAdmin = DB::table('usuarios')->where('nombre_usuario', 'admin')->value('id_usuario');
        $ahora = now();
        $auditoria = ['creado_por' => $idAdmin, 'creado_el' => $ahora, 'modificado_el' => $ahora];

        foreach (self::CATEGORIAS as [$nombre, $descripcion, $activo]) {
            DB::table('categorias')->insert(compact('nombre', 'descripcion', 'activo') + $auditoria);
        }

        foreach (self::METODOS_PAGO as [$nombre, $activo]) {
            DB::table('metodos_pago')->insert(compact('nombre', 'activo') + $auditoria);
        }

        $categorias = DB::table('categorias')->pluck('id_categoria', 'nombre');

        foreach (self::PRODUCTOS as [$nombre, $descripcion, $precio, $categoria, $activo]) {
            DB::table('productos')->insert([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'foto' => null,
                'precio' => number_format($precio, 2, '.', ''),
                'activo' => $activo,
                'id_categoria' => $categorias[$categoria],
            ] + $auditoria);
        }

        $productos = DB::table('productos as p')
            ->join('categorias as c', 'c.id_categoria', '=', 'p.id_categoria')
            ->get(['p.id_producto', 'p.nombre', 'c.nombre as categoria']);

        foreach (DB::table('sucursales')->get(['id_sucursal', 'nombre']) as $sucursal) {
            $excluidos = self::NO_DISPONIBLES[$sucursal->nombre] ?? [];
            $bajos = self::STOCK_BAJO[$sucursal->nombre] ?? [];

            foreach ($productos as $producto) {
                if (in_array($producto->nombre, $excluidos, true)) {
                    continue;
                }

                $esSaltena = $producto->categoria === 'Salteñas';
                $minimo = $esSaltena ? 30 : 10;
                $actual = $esSaltena ? mt_rand(80, 220) : mt_rand(20, 90);

                if (in_array($producto->nombre, $bajos, true)) {
                    $actual = mt_rand(0, $minimo - 1);
                }

                DB::table('producto_sucursal')->insert([
                    'id_producto' => $producto->id_producto,
                    'id_sucursal' => $sucursal->id_sucursal,
                    'stock_actual' => $actual,
                    'stock_minimo' => $minimo,
                ]);
            }
        }
    }
}
