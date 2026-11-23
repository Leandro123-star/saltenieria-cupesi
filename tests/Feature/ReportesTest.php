<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    public function test_las_seis_interfaces_de_reportes_cargan_sin_registros(): void
    {
        $sucursal = $this->createSucursal('Sucursal de reportes');
        $usuario = $this->createUsuario(['generar_reportes', 'consultar_inventario'], [$sucursal]);
        $this->actingAs($usuario)->withSession(['sucursal_activa_id' => $sucursal->getKey()]);

        foreach (['ventas-periodo', 'ventas-sucursal', 'productos-mas-vendidos', 'existencias', 'pagos', 'cajas'] as $reporte) {
            $this->get('/reportes/'.$reporte)->assertOk()->assertSee('No');
            $this->get('/reportes/'.$reporte.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_pagos_mixtos_se_suman_desde_venta_pago(): void
    {
        $sucursal = $this->createSucursal();
        $categoria = $this->createCategoria();
        $producto = $this->createProducto($categoria);
        $efectivo = $this->createMetodoPago('Efectivo');
        $qr = $this->createMetodoPago('QR');
        $usuario = $this->createUsuario(['generar_reportes'], [$sucursal]);
        $ahora = now();
        $caja = DB::table('cajas')->insertGetId([
            'fecha_apertura' => $ahora, 'monto_inicial' => 0, 'estado' => 'ABIERTA',
            'creado_el' => $ahora, 'id_sucursal' => $sucursal->getKey(), 'id_usuario_apertura' => $usuario->getKey(),
        ], 'id_caja');
        $venta = DB::table('ventas')->insertGetId([
            'fecha_venta' => $ahora, 'total' => 100, 'estado' => 'CONFIRMADA', 'creado_el' => $ahora,
            'id_sucursal' => $sucursal->getKey(), 'id_usuario' => $usuario->getKey(), 'id_caja' => $caja,
        ], 'id_venta');
        DB::table('detalle_venta')->insert(['cantidad' => 1, 'precio_unitario' => 100, 'subtotal' => 100, 'id_venta' => $venta, 'id_producto' => $producto->getKey()]);
        DB::table('venta_pago')->insert([
            ['importe' => 60, 'verificado' => true, 'id_venta' => $venta, 'id_metodo_pago' => $efectivo->getKey()],
            ['importe' => 40, 'verificado' => true, 'id_venta' => $venta, 'id_metodo_pago' => $qr->getKey()],
        ]);

        $response = $this->actingAs($usuario)->withSession(['sucursal_activa_id' => $sucursal->getKey()])->get('/reportes/pagos');

        $response->assertOk()->assertSee('60,00')->assertSee('40,00')->assertSee('100,00');
    }
}
