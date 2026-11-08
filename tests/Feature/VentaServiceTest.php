<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VentaServiceTest extends TestCase
{
    public function test_registra_venta_detalles_pagos_y_descuenta_stock(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $categoria = $this->createCategoria();
        $productoA = $this->createProducto($categoria, ['precio' => '12.50']);
        $productoB = $this->createProducto($categoria, ['precio' => '7.50']);
        $efectivo = $this->createMetodoPago('Efectivo');
        $qr = $this->createMetodoPago('QR');
        $caja = Caja::create([
            'fecha_apertura' => now(), 'monto_inicial' => '50.00', 'estado' => 'ABIERTA',
            'id_sucursal' => $sucursal->getKey(), 'id_usuario_apertura' => $usuario->getKey(),
        ]);
        $sucursal->productos()->attach($productoA, ['stock_actual' => 10, 'stock_minimo' => 2]);
        $sucursal->productos()->attach($productoB, ['stock_actual' => 10, 'stock_minimo' => 2]);

        $venta = app(VentaService::class)->registrar([
            'id_sucursal' => $sucursal->getKey(),
            'detalles' => [
                ['id_producto' => $productoA->getKey(), 'cantidad' => 2],
                ['id_producto' => $productoB->getKey(), 'cantidad' => 1],
            ],
            'pagos' => [
                ['id_metodo_pago' => $efectivo->getKey(), 'importe' => '20.00'],
                ['id_metodo_pago' => $qr->getKey(), 'importe' => '12.50'],
            ],
        ], $usuario->getKey());

        $this->assertDatabaseHas('ventas', ['id_venta' => $venta->getKey(), 'total' => '32.50']);
        $this->assertDatabaseCount('detalle_venta', 2);
        $this->assertDatabaseCount('venta_pago', 2);
        $this->assertDatabaseHas('producto_sucursal', [
            'id_producto' => $productoA->getKey(), 'id_sucursal' => $sucursal->getKey(), 'stock_actual' => 8,
        ]);
        $this->assertDatabaseHas('producto_sucursal', [
            'id_producto' => $productoB->getKey(), 'id_sucursal' => $sucursal->getKey(), 'stock_actual' => 9,
        ]);
    }

    public function test_rechaza_venta_sin_stock_y_no_deja_registros_parciales(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $categoria = $this->createCategoria();
        $producto = $this->createProducto($categoria, ['precio' => '10.00']);
        $metodo = $this->createMetodoPago('Efectivo');
        Caja::create([
            'fecha_apertura' => now(), 'monto_inicial' => '0.00', 'estado' => 'ABIERTA',
            'id_sucursal' => $sucursal->getKey(), 'id_usuario_apertura' => $usuario->getKey(),
        ]);
        $sucursal->productos()->attach($producto, ['stock_actual' => 1, 'stock_minimo' => 0]);

        try {
            app(VentaService::class)->registrar([
                'id_sucursal' => $sucursal->getKey(),
                'detalles' => [['id_producto' => $producto->getKey(), 'cantidad' => 2]],
                'pagos' => [['id_metodo_pago' => $metodo->getKey(), 'importe' => '20.00']],
            ], $usuario->getKey());
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('detalles', $exception->errors());
        }

        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('detalle_venta', 0);
        $this->assertDatabaseCount('venta_pago', 0);
        $this->assertDatabaseHas('producto_sucursal', [
            'id_producto' => $producto->getKey(), 'stock_actual' => 1,
        ]);
    }

    public function test_rechaza_venta_sin_caja_abierta(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['registrar_ventas'], [$sucursal]);

        $this->expectException(ValidationException::class);
        app(VentaService::class)->registrar([
            'id_sucursal' => $sucursal->getKey(), 'detalles' => [], 'pagos' => [],
        ], $usuario->getKey());
    }
}
