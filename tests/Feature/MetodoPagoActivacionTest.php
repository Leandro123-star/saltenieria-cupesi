<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\MetodoPago;
use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetodoPagoActivacionTest extends TestCase
{
    private Sucursal $sucursal;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sucursal = $this->createSucursal('Sucursal Centro');
        $this->admin = $this->createUsuario(
            ['gestionar_metodos_pago', 'registrar_ventas', 'generar_reportes'],
            [$this->sucursal],
            'Administrador'
        );
    }

    private function comoAdmin()
    {
        return $this->actingAs($this->admin)->withSession(['sucursal_activa_id' => $this->sucursal->getKey()]);
    }

    /** Registra una venta real por el flujo de ventas y devuelve [id_venta, producto]. */
    private function registrarVenta(MetodoPago $metodo, string $importe = '25.00'): array
    {
        $producto = $this->createProducto($this->createCategoria(), ['precio' => $importe]);
        $this->sucursal->productos()->attach($producto, ['stock_actual' => 10, 'stock_minimo' => 1]);
        if (! Caja::where('id_sucursal', $this->sucursal->getKey())->where('estado', 'ABIERTA')->exists()) {
            Caja::create([
                'fecha_apertura' => now(), 'monto_inicial' => '0.00', 'estado' => 'ABIERTA',
                'id_sucursal' => $this->sucursal->getKey(), 'id_usuario_apertura' => $this->admin->getKey(),
            ]);
        }

        $id = $this->comoAdmin()->postJson(route('ventas.store'), [
            'id_sucursal' => $this->sucursal->getKey(),
            'detalles' => [['id_producto' => $producto->getKey(), 'cantidad' => 1]],
            'pagos' => [['id_metodo_pago' => $metodo->getKey(), 'importe' => $importe]],
        ])->assertCreated()->json('id_venta');

        return [$id, $producto];
    }

    public function test_desactiva_metodo_con_pagos_historicos_sin_alterarlos_y_lo_reactiva_desde_la_tabla(): void
    {
        $metodo = $this->createMetodoPago('QR');
        [$idVenta] = $this->registrarVenta($metodo);
        $pagoAntes = DB::table('venta_pago')->where('id_venta', $idVenta)->first();
        $ventaAntes = DB::table('ventas')->where('id_venta', $idVenta)->first();
        $cajaAntes = DB::table('cajas')->where('id_caja', $ventaAntes->id_caja)->first();

        $this->comoAdmin()
            ->delete(route('metodos-pago.destroy', $metodo))
            ->assertRedirect(route('metodos-pago.index'))
            ->assertSessionHas('success', 'Método de pago desactivado correctamente.');

        $metodo->refresh();
        $this->assertFalse($metodo->activo);
        $this->assertSame($this->admin->getKey(), $metodo->modificado_por);
        $this->assertEquals($pagoAntes, DB::table('venta_pago')->where('id_venta', $idVenta)->first());
        $this->assertEquals($ventaAntes, DB::table('ventas')->where('id_venta', $idVenta)->first());
        $this->assertEquals($cajaAntes, DB::table('cajas')->where('id_caja', $ventaAntes->id_caja)->first());

        $this->comoAdmin()
            ->patch(route('metodos-pago.restore', $metodo))
            ->assertRedirect(route('metodos-pago.index'))
            ->assertSessionHas('success', 'Método de pago activado correctamente.');

        $this->assertTrue($metodo->fresh()->activo);
        $this->assertEquals($pagoAntes, DB::table('venta_pago')->where('id_venta', $idVenta)->first());
    }

    public function test_cambiar_al_mismo_estado_muestra_mensaje_sin_error(): void
    {
        $metodo = $this->createMetodoPago('Efectivo');

        $this->comoAdmin()->patch(route('metodos-pago.restore', $metodo))
            ->assertRedirect(route('metodos-pago.index'))
            ->assertSessionHas('error', 'El método de pago ya está activo.');

        $metodo->update(['activo' => false]);

        $this->comoAdmin()->delete(route('metodos-pago.destroy', $metodo))
            ->assertRedirect(route('metodos-pago.index'))
            ->assertSessionHas('error', 'El método de pago ya está inactivo.');
    }

    public function test_metodo_inactivo_no_se_ofrece_ni_se_acepta_en_nuevas_ventas_y_vuelve_al_activarlo(): void
    {
        $this->createMetodoPago('Efectivo');
        $qr = $this->createMetodoPago('QR');
        $this->registrarVenta($qr);
        $this->comoAdmin()->delete(route('metodos-pago.destroy', $qr));

        $metodos = collect($this->comoAdmin()->get(route('ventas.create'))->assertOk()->viewData('metodosData'));
        $this->assertSame(['Efectivo'], $metodos->pluck('name')->all());

        $producto = $this->createProducto($this->createCategoria(), ['precio' => '10.00']);
        $this->sucursal->productos()->attach($producto, ['stock_actual' => 5, 'stock_minimo' => 1]);
        $this->comoAdmin()->postJson(route('ventas.store'), [
            'id_sucursal' => $this->sucursal->getKey(),
            'detalles' => [['id_producto' => $producto->getKey(), 'cantidad' => 1]],
            'pagos' => [['id_metodo_pago' => $qr->getKey(), 'importe' => '10.00']],
        ])->assertStatus(422)->assertJsonValidationErrors('pagos');

        $this->comoAdmin()->patch(route('metodos-pago.restore', $qr));
        $metodos = collect($this->comoAdmin()->get(route('ventas.create'))->viewData('metodosData'));
        $this->assertContains('QR', $metodos->pluck('name')->all());
    }

    public function test_metodo_inactivo_sigue_en_detalle_de_venta_y_en_reporte_de_pagos(): void
    {
        $metodo = $this->createMetodoPago('Transferencia');
        [$idVenta] = $this->registrarVenta($metodo, '37.50');
        $this->comoAdmin()->delete(route('metodos-pago.destroy', $metodo));
        $this->assertFalse($metodo->fresh()->activo);

        $this->comoAdmin()->get(route('ventas.show', $idVenta))
            ->assertOk()
            ->assertSee('Transferencia');

        $this->comoAdmin()->get(route('reportes.pagos'))
            ->assertOk()
            ->assertSee('Transferencia')
            ->assertSee('37,50');
    }

    public function test_tabla_muestra_todos_los_metodos_con_su_accion_y_sin_filtros(): void
    {
        $activo = $this->createMetodoPago('Efectivo');
        $inactivo = $this->createMetodoPago('Tarjeta');
        $inactivo->update(['activo' => false]);

        $html = $this->comoAdmin()->get(route('metodos-pago.index'))
            ->assertOk()
            ->assertSee('Nuevo método de pago')
            ->assertDontSee('Buscar por nombre')
            ->assertDontSee('Limpiar')
            ->assertDontSee('Reactivar método de pago inactivo')
            ->getContent();

        $fila = function (string $nombre) use ($html): string {
            $this->assertSame(1, preg_match('#<tr><td><strong>'.preg_quote($nombre, '#').'</strong>.*?</tr>#s', $html, $m));

            return $m[0];
        };

        $filaActiva = $fila('Efectivo');
        $this->assertStringContainsString('Activo<', $filaActiva);
        $this->assertStringContainsString(route('metodos-pago.destroy', $activo), $filaActiva);
        $this->assertStringContainsString('>Desactivar<', $filaActiva);
        $this->assertStringNotContainsString('>Activar<', $filaActiva);

        $filaInactiva = $fila('Tarjeta');
        $this->assertStringContainsString('Inactivo<', $filaInactiva);
        $this->assertStringContainsString(route('metodos-pago.restore', $inactivo), $filaInactiva);
        $this->assertStringContainsString('>Activar<', $filaInactiva);
        $this->assertStringContainsString('>Editar<', $filaInactiva);
        $this->assertStringNotContainsString('>Desactivar<', $filaInactiva);
    }

    public function test_creacion_y_edicion_siguen_funcionando(): void
    {
        $this->comoAdmin()->post(route('metodos-pago.store'), ['nombre' => 'Billetera móvil', 'activo' => '1'])
            ->assertRedirect(route('metodos-pago.index'));
        $metodo = MetodoPago::where('nombre', 'Billetera móvil')->firstOrFail();

        $this->comoAdmin()->put(route('metodos-pago.update', $metodo), ['nombre' => 'Billetera digital', 'activo' => '1'])
            ->assertRedirect(route('metodos-pago.index'))
            ->assertSessionHas('success');
        $this->assertSame('Billetera digital', $metodo->fresh()->nombre);
    }
}
