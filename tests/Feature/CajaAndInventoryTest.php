<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CajaAndInventoryTest extends TestCase
{
    public function test_abre_una_caja_y_impide_otra_abierta_en_la_misma_sucursal(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['gestionar_caja'], [$sucursal]);

        $this->actingAs($usuario)->postJson('/cajas/abrir', [
            'id_sucursal' => $sucursal->getKey(), 'monto_inicial' => '100.00',
        ])->assertCreated();

        $this->actingAs($usuario)->postJson('/cajas/abrir', [
            'id_sucursal' => $sucursal->getKey(), 'monto_inicial' => '50.00',
        ])->assertStatus(422);

        $this->assertDatabaseCount('cajas', 1);
    }

    public function test_cierra_caja_sumando_solo_efectivo_y_calcula_diferencia(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['gestionar_caja', 'registrar_ventas'], [$sucursal]);
        $categoria = $this->createCategoria();
        $producto = $this->createProducto($categoria, ['precio' => '30.00']);
        $efectivo = $this->createMetodoPago('Efectivo');
        $qr = $this->createMetodoPago('QR');
        $caja = Caja::create([
            'fecha_apertura' => now(), 'monto_inicial' => '100.00', 'estado' => 'ABIERTA',
            'id_sucursal' => $sucursal->getKey(), 'id_usuario_apertura' => $usuario->getKey(),
        ]);
        $sucursal->productos()->attach($producto, ['stock_actual' => 10, 'stock_minimo' => 2]);

        app(VentaService::class)->registrar([
            'id_sucursal' => $sucursal->getKey(),
            'detalles' => [['id_producto' => $producto->getKey(), 'cantidad' => 2]],
            'pagos' => [
                ['id_metodo_pago' => $efectivo->getKey(), 'importe' => '30.00'],
                ['id_metodo_pago' => $qr->getKey(), 'importe' => '30.00'],
            ],
        ], $usuario->getKey());

        $response = $this->actingAs($usuario)->postJson('/cajas/'.$caja->getKey().'/cerrar', [
            'efectivo_contado' => '125.00',
        ]);

        $response->assertOk()->assertJsonPath('efectivo_esperado', '130.00')
            ->assertJsonPath('diferencia', '-5.00');
        $this->assertDatabaseHas('cajas', [
            'id_caja' => $caja->getKey(), 'estado' => 'CERRADA', 'efectivo_esperado' => '130.00', 'diferencia' => '-5.00',
        ]);
    }

    public function test_registra_producto_y_consulta_existencias_independientes_por_sucursal(): void
    {
        $primera = $this->createSucursal();
        $segunda = $this->createSucursal();
        $admin = $this->createUsuario([], [], 'Administrador');
        $categoria = $this->createCategoria();

        $response = $this->actingAs($admin)->postJson('/productos', [
            'nombre' => 'Salteña de prueba', 'descripcion' => 'Unidad', 'precio' => '8.50',
            'activo' => true, 'id_categoria' => $categoria->getKey(),
        ]);
        $response->assertCreated();
        $productoId = $response->json('id_producto');

        DB::table('producto_sucursal')->insert([
            ['id_producto' => $productoId, 'id_sucursal' => $primera->getKey(), 'stock_actual' => 12, 'stock_minimo' => 3],
            ['id_producto' => $productoId, 'id_sucursal' => $segunda->getKey(), 'stock_actual' => 4, 'stock_minimo' => 2],
        ]);

        $response = $this->actingAs($admin)->getJson('/inventario?id_sucursal='.$primera->getKey());
        $response->assertOk()->assertJsonPath('data.0.stock_actual', 12);
        $this->assertDatabaseHas('producto_sucursal', [
            'id_producto' => $productoId, 'id_sucursal' => $segunda->getKey(), 'stock_actual' => 4,
        ]);
    }

    public function test_rechaza_existencias_negativas_en_postgresql(): void
    {
        $sucursal = $this->createSucursal();
        $categoria = $this->createCategoria();
        $producto = $this->createProducto($categoria);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('producto_sucursal')->insert([
            'id_producto' => $producto->getKey(), 'id_sucursal' => $sucursal->getKey(),
            'stock_actual' => -1, 'stock_minimo' => 0,
        ]);
    }
}
