<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Sucursal;
use App\Models\Usuario;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VentaFlowTest extends TestCase
{
    private function cajaPara(Sucursal $sucursal, Usuario $usuario): Caja
    {
        return Caja::create([
            'fecha_apertura' => now(),
            'monto_inicial' => '0.00',
            'estado' => 'ABIERTA',
            'id_sucursal' => $sucursal->getKey(),
            'id_usuario_apertura' => $usuario->getKey(),
        ]);
    }

    private function ventaDirecta(Sucursal $sucursal, Usuario $usuario, Caja $caja, string $fecha, string $total): int
    {
        return DB::table('ventas')->insertGetId([
            'fecha_venta' => $fecha,
            'total' => $total,
            'estado' => 'CONFIRMADA',
            'id_sucursal' => $sucursal->getKey(),
            'id_usuario' => $usuario->getKey(),
            'id_caja' => $caja->getKey(),
        ], 'id_venta');
    }

    private function contextoDeVenta(Sucursal $sucursal, Usuario $usuario): array
    {
        $categoria = $this->createCategoria();
        $producto = $this->createProducto($categoria, ['precio' => '10.00']);
        $metodo = $this->createMetodoPago('Efectivo');
        $this->cajaPara($sucursal, $usuario);
        $sucursal->productos()->attach($producto, ['stock_actual' => 5, 'stock_minimo' => 1]);

        return [
            'payload' => [
                'id_sucursal' => $sucursal->getKey(),
                'detalles' => [['id_producto' => $producto->getKey(), 'cantidad' => 1]],
                'pagos' => [['id_metodo_pago' => $metodo->getKey(), 'importe' => '10.00']],
            ],
            'producto' => $producto,
            'metodo' => $metodo,
        ];
    }

    public function test_el_modulo_ventas_abre_el_historial_y_no_el_formulario(): void
    {
        $sucursal = $this->createSucursal();
        $cajero = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $caja = $this->cajaPara($sucursal, $cajero);
        $this->ventaDirecta($sucursal, $cajero, $caja, now()->format('Y-m-d H:i:s'), '10.00');

        $this->actingAs($cajero)->get('/ventas')
            ->assertOk()
            ->assertViewIs('ventas.index')
            ->assertSee('Historial de ventas')
            ->assertSee('+ Registrar venta')
            ->assertSee(route('ventas.create'), false)
            ->assertSee('Ver detalle de venta')
            ->assertDontSee('Confirmar venta');
    }

    public function test_boton_registrar_venta_abre_el_formulario_de_registro(): void
    {
        $sucursal = $this->createSucursal();
        $cajero = $this->createUsuario(['registrar_ventas'], [$sucursal]);

        $this->actingAs($cajero)->get('/ventas/create')
            ->assertOk()
            ->assertViewIs('ventas.create')
            ->assertSee('Registrar venta')
            ->assertSee('<a class="btn out" href="'.route('ventas.index').'">← Volver</a>', false)
            ->assertSee('Confirmar venta');
    }

    public function test_registrar_venta_redirige_al_historial_y_muestra_confirmacion(): void
    {
        $sucursal = $this->createSucursal();
        $cajero = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $contexto = $this->contextoDeVenta($sucursal, $cajero);

        $response = $this->actingAs($cajero)->post('/ventas', $contexto['payload']);

        $response->assertRedirect(route('ventas.index'))->assertSessionHas('success');

        $this->get(route('ventas.index'))
            ->assertOk()
            ->assertSee('Venta registrada correctamente.')
            ->assertSee('Bs 10,00')
            ->assertSee('Ver detalle de venta');
    }

    public function test_registrar_venta_desde_el_formulario_json_deja_confirmacion(): void
    {
        $sucursal = $this->createSucursal();
        $cajero = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $contexto = $this->contextoDeVenta($sucursal, $cajero);

        $this->actingAs($cajero)->postJson('/ventas', $contexto['payload'])
            ->assertCreated()
            ->assertJsonPath('total', '10.00')
            ->assertSessionHas('success');

        $this->get(route('ventas.index'))
            ->assertOk()
            ->assertSee('Venta registrada correctamente.');
    }

    public function test_detalle_muestra_productos_pagos_y_total_sin_duplicar_importes(): void
    {
        $sucursal = $this->createSucursal();
        $cajero = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $categoria = $this->createCategoria();
        $producto = $this->createProducto($categoria, ['precio' => '10.00']);
        $efectivo = $this->createMetodoPago('Efectivo');
        $qr = $this->createMetodoPago('QR');
        $this->cajaPara($sucursal, $cajero);
        $sucursal->productos()->attach($producto, ['stock_actual' => 5, 'stock_minimo' => 1]);

        $venta = app(VentaService::class)->registrar([
            'id_sucursal' => $sucursal->getKey(),
            'detalles' => [['id_producto' => $producto->getKey(), 'cantidad' => 2]],
            'pagos' => [
                ['id_metodo_pago' => $efectivo->getKey(), 'importe' => '12.34'],
                ['id_metodo_pago' => $qr->getKey(), 'importe' => '7.66'],
            ],
        ], $cajero->getKey());

        $response = $this->actingAs($cajero)->get('/ventas/'.$venta->getKey());

        $response->assertOk()
            ->assertViewIs('ventas.show')
            ->assertSee('Detalle de venta #'.$venta->getKey())
            ->assertSee($producto->nombre)
            ->assertSee('Bs 10,00')
            ->assertSee('Bs 20,00')
            ->assertSee('Efectivo')
            ->assertSee('QR')
            ->assertSee('Bs 12,34')
            ->assertSee('Bs 7,66')
            ->assertSee('CONFIRMADA')
            ->assertSee('Volver al historial')
            ->assertSeeInOrder(['Detalle de productos', 'Métodos de pago', 'Bs 12,34', 'Bs 7,66', 'Total de la venta', 'Bs 20,00'])
            ->assertDontSee('Resumen económico')
            ->assertDontSee('Total pagado')
            ->assertDontSee('Saldo pendiente');

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'Total de la venta'));
        $this->assertSame(1, substr_count($html, 'Bs 12,34'));
        $this->assertSame(1, substr_count($html, 'Bs 7,66'));
    }

    public function test_filtra_ventas_por_rango_de_fechas_y_ordena_de_mas_reciente_a_mas_antigua(): void
    {
        $sucursal = $this->createSucursal();
        $cajero = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $caja = $this->cajaPara($sucursal, $cajero);
        $this->ventaDirecta($sucursal, $cajero, $caja, '2026-01-10 10:00:00', '11.11');
        $this->ventaDirecta($sucursal, $cajero, $caja, '2026-02-10 10:00:00', '22.22');

        $this->actingAs($cajero)->get('/ventas?desde=2026-02-01&hasta=2026-02-28')
            ->assertOk()
            ->assertSee('Bs 22,22')
            ->assertDontSee('Bs 11,11');

        $this->actingAs($cajero)->get('/ventas')
            ->assertOk()
            ->assertSeeInOrder(['Bs 22,22', 'Bs 11,11']);
    }

    public function test_cajero_solo_consulta_las_ventas_de_su_sucursal(): void
    {
        $sucursalA = $this->createSucursal('Sucursal A');
        $sucursalB = $this->createSucursal('Sucursal B');
        $cajeroA = $this->createUsuario(['registrar_ventas'], [$sucursalA]);
        $cajeroB = $this->createUsuario(['registrar_ventas'], [$sucursalB]);
        $cajaA = $this->cajaPara($sucursalA, $cajeroA);
        $cajaB = $this->cajaPara($sucursalB, $cajeroB);
        $ventaA = $this->ventaDirecta($sucursalA, $cajeroA, $cajaA, now()->format('Y-m-d H:i:s'), '33.33');
        $ventaB = $this->ventaDirecta($sucursalB, $cajeroB, $cajaB, now()->format('Y-m-d H:i:s'), '44.44');

        $this->actingAs($cajeroA)->get('/ventas')
            ->assertOk()
            ->assertSee('Bs 33,33')
            ->assertDontSee('Bs 44,44');

        $this->actingAs($cajeroA)->get('/ventas/'.$ventaA)->assertOk();
        $this->actingAs($cajeroA)->get('/ventas/'.$ventaB)->assertForbidden();
    }

    public function test_administrador_solo_ve_las_sucursales_que_tiene_autorizadas(): void
    {
        $sucursalA = $this->createSucursal('Sucursal A');
        $sucursalB = $this->createSucursal('Sucursal B');
        $admin = $this->createUsuario([], [$sucursalA], 'Administrador');
        $cajeroB = $this->createUsuario(['registrar_ventas'], [$sucursalB]);
        $cajaA = $this->cajaPara($sucursalA, $admin);
        $cajaB = $this->cajaPara($sucursalB, $cajeroB);
        $this->ventaDirecta($sucursalA, $admin, $cajaA, now()->format('Y-m-d H:i:s'), '55.55');
        $this->ventaDirecta($sucursalB, $cajeroB, $cajaB, now()->format('Y-m-d H:i:s'), '66.66');

        $this->actingAs($admin)->get('/ventas')
            ->assertOk()
            ->assertSee('Bs 55,55')
            ->assertDontSee('Bs 66,66');
    }
}
