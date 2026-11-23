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
            'qr_verificado' => '30.00',
        ]);

        $response->assertOk()->assertJsonPath('efectivo_esperado', '130.00')
            ->assertJsonPath('qr_esperado', '30.00')
            ->assertJsonPath('diferencia', '-5.00')
            ->assertJsonPath('diferencia_qr', '0.00')
            ->assertJsonPath('diferencia_total', '-5.00');
        $this->assertDatabaseHas('cajas', [
            'id_caja' => $caja->getKey(), 'estado' => 'CERRADA', 'efectivo_esperado' => '130.00', 'diferencia' => '-5.00',
            'qr_verificado' => '30.00', 'qr_esperado' => '30.00', 'diferencia_qr' => '0.00', 'diferencia_total' => '-5.00',
        ]);
    }

    public function test_cierra_caja_sin_ventas_y_exige_ambos_arqueos(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['gestionar_caja'], [$sucursal]);
        $caja = Caja::create([
            'fecha_apertura' => now(), 'monto_inicial' => '0.00', 'estado' => 'ABIERTA',
            'id_sucursal' => $sucursal->getKey(), 'id_usuario_apertura' => $usuario->getKey(),
        ]);

        $this->actingAs($usuario)->postJson('/cajas/'.$caja->getKey().'/cerrar', [
            'efectivo_contado' => '0.00',
        ])->assertUnprocessable()->assertJsonValidationErrors('qr_verificado');

        $this->actingAs($usuario)->postJson('/cajas/'.$caja->getKey().'/cerrar', [
            'efectivo_contado' => '0.00', 'qr_verificado' => '0.00',
        ])->assertOk()->assertJsonPath('efectivo_esperado', '0.00')
            ->assertJsonPath('qr_esperado', '0.00')
            ->assertJsonPath('diferencia_total', '0.00');
    }

    public function test_muestra_resultado_de_caja_con_ventas_y_pagos_por_metodo(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['gestionar_caja', 'registrar_ventas'], [$sucursal], 'Administrador');
        $categoria = $this->createCategoria();
        $producto = $this->createProducto($categoria, ['precio' => '20.00']);
        $efectivo = $this->createMetodoPago('Efectivo');
        $qr = $this->createMetodoPago('QR');
        $transferencia = $this->createMetodoPago('Transferencia');
        $caja = Caja::create([
            'fecha_apertura' => now(), 'monto_inicial' => '10.00', 'estado' => 'ABIERTA',
            'id_sucursal' => $sucursal->getKey(), 'id_usuario_apertura' => $usuario->getKey(),
        ]);
        $sucursal->productos()->attach($producto, ['stock_actual' => 10, 'stock_minimo' => 2]);

        app(VentaService::class)->registrar([
            'id_sucursal' => $sucursal->getKey(),
            'detalles' => [['id_producto' => $producto->getKey(), 'cantidad' => 1]],
            'pagos' => [
                ['id_metodo_pago' => $efectivo->getKey(), 'importe' => '10.00'],
                ['id_metodo_pago' => $qr->getKey(), 'importe' => '5.00'],
                ['id_metodo_pago' => $transferencia->getKey(), 'importe' => '5.00'],
            ],
        ], $usuario->getKey());

        $this->actingAs($usuario)->postJson('/cajas/'.$caja->getKey().'/cerrar', [
            'efectivo_contado' => '20.00',
            'qr_verificado' => '5.00',
        ])->assertOk();

        $response = $this->actingAs($usuario)->get('/cajas/'.$caja->getKey());

        $response->assertOk()->assertViewIs('cajas.show')->assertViewHas('resultado', function (array $resultado): bool {
            return $resultado['ventas'] === 1
                && $resultado['vendido'] === 2000
                && $resultado['efectivo'] === 1000
                && $resultado['qr'] === 500
                && $resultado['transferencia'] === 500
                && $resultado['efectivo_esperado'] === 2000
                && $resultado['diferencia'] === 0
                && $resultado['qr_esperado'] === 500
                && $resultado['qr_verificado'] === 500
                && $resultado['diferencia_qr'] === 0
                && $resultado['diferencia_total'] === 0
                && $resultado['clasificacion'] === 'Caja cuadrada';
        });
    }

    public function test_no_permite_consultar_caja_de_otra_sucursal(): void
    {
        $asignada = $this->createSucursal();
        $noAsignada = $this->createSucursal();
        $usuario = $this->createUsuario(['gestionar_caja'], [$asignada]);
        $caja = Caja::create([
            'fecha_apertura' => now(), 'monto_inicial' => '0.00', 'estado' => 'ABIERTA',
            'id_sucursal' => $noAsignada->getKey(), 'id_usuario_apertura' => $usuario->getKey(),
        ]);

        $this->actingAs($usuario)->getJson('/cajas/'.$caja->getKey())->assertForbidden();
    }

    public function test_cajero_no_puede_consultar_resultado_de_su_propia_caja(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['gestionar_caja'], [$sucursal]);
        $caja = Caja::create([
            'fecha_apertura' => now(), 'monto_inicial' => '0.00', 'estado' => 'ABIERTA',
            'id_sucursal' => $sucursal->getKey(), 'id_usuario_apertura' => $usuario->getKey(),
        ]);

        $this->actingAs($usuario)->getJson('/cajas/'.$caja->getKey())->assertForbidden();
    }

    public function test_administrador_puede_ver_caja_historica_con_diferencia_negativa_y_qr_no_registrado(): void
    {
        $sucursal = $this->createSucursal();
        $admin = $this->createUsuario([], [$sucursal], 'Administrador');
        $caja = Caja::create([
            'fecha_apertura' => now()->subHour(), 'fecha_cierre' => now(), 'monto_inicial' => '100.00',
            'efectivo_contado' => '90.00', 'efectivo_esperado' => '134.00', 'diferencia' => '-44.00',
            'estado' => 'CERRADA', 'id_sucursal' => $sucursal->getKey(), 'id_usuario_apertura' => $admin->getKey(),
        ]);

        $response = $this->actingAs($admin)->get('/cajas/'.$caja->getKey());

        $response->assertOk()->assertSee('Bs -44,00')->assertSee('Faltante')->assertSee('No registrado');
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
