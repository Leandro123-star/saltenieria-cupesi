<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaCorreccion;
use App\Services\VentaService;
use App\Support\CajaCalculadora;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class VentaCorreccionTest extends TestCase
{
    private function abrirCaja(Sucursal $sucursal, Usuario $usuario): Caja
    {
        return Caja::create([
            'fecha_apertura' => now(),
            'monto_inicial' => '0.00',
            'estado' => 'ABIERTA',
            'id_sucursal' => $sucursal->getKey(),
            'id_usuario_apertura' => $usuario->getKey(),
        ]);
    }

    private function registrarVenta(Sucursal $sucursal, Usuario $usuario, array $detalles, array $pagos): Venta
    {
        return app(VentaService::class)->registrar([
            'id_sucursal' => $sucursal->getKey(),
            'detalles' => $detalles,
            'pagos' => $pagos,
        ], $usuario->getKey());
    }

    /**
     * Payload de corrección: $sustitutos mapea id_producto original => id_producto
     * final. Las líneas no indicadas se envían sin cambio. Sin $pagos no se
     * envía ningún pago, igual que el formulario cuando el total no cambia.
     */
    private function correccion(Venta $venta, array $sustitutos, ?array $pagos = null): array
    {
        $payload = [
            'motivo' => 'Se registró un producto equivocado por error de digitación.',
            'declaracion' => '1',
            'detalles' => $venta->detalles()->get()->map(fn ($detalle): array => [
                'id_detalle_venta' => $detalle->getKey(),
                'id_producto' => $sustitutos[$detalle->id_producto] ?? $detalle->id_producto,
            ])->values()->all(),
        ];
        if ($pagos !== null) {
            $payload['pagos'] = $pagos;
        }

        return $payload;
    }

    private function pago(MetodoPago $metodo, string $importe): array
    {
        return ['id_metodo_pago' => $metodo->getKey(), 'importe' => $importe];
    }

    /** @return array{0: Sucursal, 1: Usuario, 2: Producto, 3: Producto, 4: MetodoPago, 5: Caja} */
    private function escenario(string $precioPollo = '10.00', string $precioCarne = '12.00', int $stock = 10): array
    {
        $sucursal = $this->createSucursal();
        $cajero = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $categoria = $this->createCategoria();
        $pollo = $this->createProducto($categoria, ['nombre' => 'Salteña de Pollo', 'precio' => $precioPollo]);
        $carne = $this->createProducto($categoria, ['nombre' => 'Salteña de Carne', 'precio' => $precioCarne]);
        $efectivo = $this->createMetodoPago('Efectivo');
        $caja = $this->abrirCaja($sucursal, $cajero);
        $sucursal->productos()->attach([
            $pollo->getKey() => ['stock_actual' => $stock, 'stock_minimo' => 1],
            $carne->getKey() => ['stock_actual' => $stock, 'stock_minimo' => 1],
        ]);

        return [$sucursal, $cajero, $pollo, $carne, $efectivo, $caja];
    }

    private function stock(Producto $producto, Sucursal $sucursal): int
    {
        return (int) DB::table('producto_sucursal')
            ->where('id_producto', $producto->getKey())
            ->where('id_sucursal', $sucursal->getKey())
            ->value('stock_actual');
    }

    private function ventaPolloX2(Sucursal $sucursal, Usuario $cajero, Producto $pollo, MetodoPago $efectivo): Venta
    {
        return $this->registrarVenta(
            $sucursal,
            $cajero,
            [['id_producto' => $pollo->getKey(), 'cantidad' => 2]],
            [$this->pago($efectivo, number_format(2 * (float) $pollo->precio, 2, '.', ''))]
        );
    }

    public function test_sustituye_producto_actualiza_stock_total_pagos_y_auditoria(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);
        $this->assertSame(8, $this->stock($pollo, $sucursal));

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '24.00')]
            ))
            ->assertRedirect(route('ventas.show', $venta))
            ->assertSessionHas('success', 'Venta corregida correctamente.');

        $this->assertSame(10, $this->stock($pollo, $sucursal));
        $this->assertSame(8, $this->stock($carne, $sucursal));
        $this->assertDatabaseHas('ventas', ['id_venta' => $venta->getKey(), 'total' => '24.00']);

        $activa = DB::table('detalle_venta')->where('id_venta', $venta->getKey())->whereNull('reemplazado_el')->get();
        $this->assertCount(1, $activa);
        $this->assertSame($carne->getKey(), (int) $activa[0]->id_producto);
        $this->assertSame(2, (int) $activa[0]->cantidad);
        $this->assertSame('12.00', (string) $activa[0]->precio_unitario);
        $this->assertSame(1, DB::table('detalle_venta')->where('id_venta', $venta->getKey())->whereNotNull('reemplazado_el')->count());
        $this->assertSame(1, DB::table('venta_pago')->where('id_venta', $venta->getKey())->whereNotNull('reemplazado_el')->count());
        $this->assertSame('24.00', (string) DB::table('venta_pago')
            ->where('id_venta', $venta->getKey())->whereNull('reemplazado_el')->sole()->importe);

        $correccion = VentaCorreccion::where('id_venta', $venta->getKey())->firstOrFail();
        $this->assertSame('Cajero/Vendedor', $correccion->rol);
        $this->assertSame($cajero->getKey(), (int) $correccion->id_usuario);
        $this->assertSame($sucursal->getKey(), (int) $correccion->id_sucursal);
        $this->assertSame('20.00', (string) $correccion->total_anterior);
        $this->assertSame('24.00', (string) $correccion->total_posterior);
        $this->assertNotNull($correccion->creado_el);
        $this->assertStringContainsString('producto equivocado', $correccion->motivo);

        $sustitucion = $correccion->datos_posteriores['sustituciones'][0];
        $this->assertSame('Salteña de Pollo', $sustitucion['producto_anterior']['nombre']);
        $this->assertSame('Salteña de Carne', $sustitucion['producto_sustituto']['nombre']);
        $this->assertSame(2, $sustitucion['cantidad']);
        $this->assertSame('10.00', $sustitucion['precio_anterior']);
        $this->assertSame('12.00', $sustitucion['precio_nuevo']);
        $this->assertSame('Efectivo', $correccion->datos_anteriores['pagos'][0]['metodo']);
        $this->assertSame('24.00', $correccion->datos_posteriores['pagos'][0]['importe']);
    }

    public function test_sustitucion_sin_enviar_pagos_conserva_los_pagos_originales_sin_duplicarlos(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario('10.00', '10.00');
        $qr = $this->createMetodoPago('QR');
        $venta = $this->registrarVenta(
            $sucursal,
            $cajero,
            [['id_producto' => $pollo->getKey(), 'cantidad' => 2]],
            [$this->pago($efectivo, '15.00'), $this->pago($qr, '5.00')]
        );
        $idsPagos = DB::table('venta_pago')->where('id_venta', $venta->getKey())->orderBy('id_venta_pago')->pluck('id_venta_pago')->all();

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion($venta, [$pollo->getKey() => $carne->getKey()]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('ventas.show', $venta));

        $this->assertDatabaseHas('ventas', ['id_venta' => $venta->getKey(), 'total' => '20.00']);
        $this->assertSame(10, $this->stock($pollo, $sucursal));
        $this->assertSame(8, $this->stock($carne, $sucursal));
        $this->assertDatabaseCount('venta_pago', 2);
        $this->assertSame($idsPagos, DB::table('venta_pago')
            ->where('id_venta', $venta->getKey())->whereNull('reemplazado_el')
            ->orderBy('id_venta_pago')->pluck('id_venta_pago')->all());
        $this->assertSame(['15.00', '5.00'], DB::table('venta_pago')
            ->where('id_venta', $venta->getKey())->orderBy('id_venta_pago')
            ->pluck('importe')->map(fn ($i) => (string) $i)->all());
        $this->assertCount(2, VentaCorreccion::firstOrFail()->datos_posteriores['pagos']);
    }

    public function test_cambio_de_precio_sin_ajustar_pagos_se_bloquea_con_mensaje_claro(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion($venta, [$pollo->getKey() => $carne->getKey()]))
            ->assertSessionHasErrors([
                'pagos' => 'La sustitución cambia el total de Bs 20,00 a Bs 24,00. Debe cobrar al cliente Bs 4,00 y ajustar los pagos antes de confirmar.',
            ]);

        $this->assertDatabaseHas('ventas', ['id_venta' => $venta->getKey(), 'total' => '20.00']);
        $this->assertSame(8, $this->stock($pollo, $sucursal));
        $this->assertSame(10, $this->stock($carne, $sucursal));
        $this->assertSame(0, DB::table('detalle_venta')->whereNotNull('reemplazado_el')->count());
        $this->assertSame(0, DB::table('venta_pago')->whereNotNull('reemplazado_el')->count());
        $this->assertSame(0, VentaCorreccion::count());
    }

    public function test_precio_menor_sin_ajuste_indica_devolver_la_diferencia(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario('10.00', '8.50');
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion($venta, [$pollo->getKey() => $carne->getKey()]))
            ->assertSessionHasErrors([
                'pagos' => 'La sustitución cambia el total de Bs 20,00 a Bs 17,00. Debe devolver al cliente Bs 3,00 y ajustar los pagos antes de confirmar.',
            ]);
        $this->assertSame(0, VentaCorreccion::count());
    }

    public function test_pagos_ajustados_que_no_cuadran_no_dejan_cambios_parciales(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '22.00')]
            ))
            ->assertSessionHasErrors('pagos');

        $this->assertDatabaseHas('ventas', ['id_venta' => $venta->getKey(), 'total' => '20.00']);
        $this->assertSame(8, $this->stock($pollo, $sucursal));
        $this->assertSame(10, $this->stock($carne, $sucursal));
        $this->assertSame(0, DB::table('detalle_venta')->whereNotNull('reemplazado_el')->count());
        $this->assertSame(0, VentaCorreccion::count());
    }

    public function test_no_permite_agregar_lineas_nuevas(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $payload = $this->correccion($venta, [], [$this->pago($efectivo, '32.00')]);
        $payload['detalles'][] = ['id_detalle_venta' => 999999, 'id_producto' => $carne->getKey()];

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $payload)
            ->assertSessionHasErrors('detalles');

        $this->assertSame(10, $this->stock($carne, $sucursal));
        $this->assertSame(0, VentaCorreccion::count());
    }

    public function test_no_permite_quitar_lineas_ni_dejar_la_venta_vacia(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->registrarVenta(
            $sucursal,
            $cajero,
            [['id_producto' => $pollo->getKey(), 'cantidad' => 1], ['id_producto' => $carne->getKey(), 'cantidad' => 1]],
            [$this->pago($efectivo, '22.00')]
        );

        $payload = $this->correccion($venta, [], [$this->pago($efectivo, '10.00')]);
        array_pop($payload['detalles']);
        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $payload)
            ->assertSessionHasErrors('detalles');

        $payload['detalles'] = [];
        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $payload)
            ->assertSessionHasErrors('detalles');

        $this->assertSame(2, DB::table('detalle_venta')->where('id_venta', $venta->getKey())->whereNull('reemplazado_el')->count());
        $this->assertSame(0, VentaCorreccion::count());
    }

    public function test_la_cantidad_enviada_se_ignora_y_se_conserva_la_original(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $payload = $this->correccion($venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '24.00')]);
        $payload['detalles'][0]['cantidad'] = 5;

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $payload)
            ->assertRedirect(route('ventas.show', $venta));

        $this->assertSame(2, (int) DB::table('detalle_venta')
            ->where('id_venta', $venta->getKey())->whereNull('reemplazado_el')->value('cantidad'));
        $this->assertSame(8, $this->stock($carne, $sucursal));
    }

    public function test_sustituto_sin_stock_suficiente_no_deja_cambios_parciales(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);
        DB::table('producto_sucursal')->where('id_producto', $carne->getKey())->update(['stock_actual' => 1]);

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '24.00')]
            ))
            ->assertSessionHasErrors('detalles');

        $this->assertSame(8, $this->stock($pollo, $sucursal));
        $this->assertSame(1, $this->stock($carne, $sucursal));
        $this->assertDatabaseHas('ventas', ['id_venta' => $venta->getKey(), 'total' => '20.00']);
        $this->assertSame(0, VentaCorreccion::count());
    }

    public function test_sustituto_inactivo_o_de_otra_sucursal_es_rechazado(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $ajeno = $this->createProducto($this->createCategoria(), ['precio' => '10.00']);
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $carne->update(['activo' => false]);
        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '24.00')]
            ))
            ->assertSessionHasErrors('detalles');

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $ajeno->getKey()], [$this->pago($efectivo, '20.00')]
            ))
            ->assertSessionHasErrors('detalles');

        $this->assertSame(8, $this->stock($pollo, $sucursal));
        $this->assertSame(0, VentaCorreccion::count());
    }

    public function test_sin_sustitucion_no_hay_correccion_aunque_se_envien_pagos(): void
    {
        [$sucursal, $cajero, $pollo, , $efectivo] = $this->escenario();
        $qr = $this->createMetodoPago('QR');
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion($venta, []))
            ->assertSessionHasErrors('detalles');
        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion($venta, [], [$this->pago($qr, '20.00')]))
            ->assertSessionHasErrors('detalles');

        $this->assertSame(0, DB::table('venta_pago')->whereNotNull('reemplazado_el')->count());
        $this->assertSame(0, VentaCorreccion::count());
    }

    public function test_pago_ajustado_sin_importe_muestra_mensaje_en_espanol_una_sola_vez(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);
        $payload = $this->correccion($venta, [$pollo->getKey() => $carne->getKey()], [['id_metodo_pago' => $efectivo->getKey()]]);

        $html = $this->actingAs($cajero)
            ->from(route('ventas.corregir', $venta))
            ->followingRedirects()
            ->put(route('ventas.corregir.update', $venta), $payload)
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Indique el importe de cada pago ajustado.'));
        $this->assertStringNotContainsString('pagos.0.importe', $html);
        $this->assertSame(0, VentaCorreccion::count());
    }

    public function test_error_de_negocio_se_muestra_una_sola_vez(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $html = $this->actingAs($cajero)
            ->from(route('ventas.corregir', $venta))
            ->followingRedirects()
            ->put(route('ventas.corregir.update', $venta), $this->correccion($venta, [$pollo->getKey() => $carne->getKey()]))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'La sustitución cambia el total de Bs 20,00 a Bs 24,00.'));
    }

    public function test_sustituto_ya_presente_en_la_venta_se_fusiona_en_una_linea(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->registrarVenta(
            $sucursal,
            $cajero,
            [['id_producto' => $pollo->getKey(), 'cantidad' => 2], ['id_producto' => $carne->getKey(), 'cantidad' => 1]],
            [$this->pago($efectivo, '32.00')]
        );

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '36.00')]
            ))
            ->assertRedirect(route('ventas.show', $venta));

        $activas = DB::table('detalle_venta')->where('id_venta', $venta->getKey())->whereNull('reemplazado_el')->get();
        $this->assertCount(1, $activas);
        $this->assertSame(3, (int) $activas[0]->cantidad);
        $this->assertSame('36.00', (string) $activas[0]->subtotal);
        $this->assertSame(10, $this->stock($pollo, $sucursal));
        $this->assertSame(7, $this->stock($carne, $sucursal));
    }

    public function test_linea_sin_cambio_conserva_precio_historico_aunque_el_producto_este_inactivo(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $papa = $this->createProducto($this->createCategoria(), ['precio' => '5.00']);
        $sucursal->productos()->attach($papa, ['stock_actual' => 10, 'stock_minimo' => 1]);
        $venta = $this->registrarVenta(
            $sucursal,
            $cajero,
            [['id_producto' => $pollo->getKey(), 'cantidad' => 1], ['id_producto' => $papa->getKey(), 'cantidad' => 1]],
            [$this->pago($efectivo, '15.00')]
        );
        $papa->update(['activo' => false, 'precio' => '6.00']);

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '17.00')]
            ))
            ->assertRedirect(route('ventas.show', $venta));

        $this->assertDatabaseHas('ventas', ['id_venta' => $venta->getKey(), 'total' => '17.00']);
        $this->assertSame('5.00', (string) DB::table('detalle_venta')
            ->where('id_venta', $venta->getKey())->whereNull('reemplazado_el')
            ->where('id_producto', $papa->getKey())->value('precio_unitario'));
        $this->assertSame(9, $this->stock($papa, $sucursal));
    }

    public function test_cajero_no_puede_corregir_venta_de_otro_cajero(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $otro = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $this->actingAs($otro)->get(route('ventas.corregir', $venta))->assertForbidden();
        $this->actingAs($otro)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '24.00')]
            ))
            ->assertForbidden();

        $this->assertSame(0, VentaCorreccion::count());
    }

    public function test_cajero_no_puede_corregir_venta_de_otra_sucursal(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $cajeroB = $this->createUsuario(['registrar_ventas'], [$this->createSucursal('Sucursal B')]);
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $this->actingAs($cajeroB)->get(route('ventas.corregir', $venta))->assertForbidden();
        $this->actingAs($cajeroB)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '24.00')]
            ))
            ->assertForbidden();
    }

    public function test_administrador_corrige_en_sucursal_autorizada_pero_no_en_otra(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $admin = $this->createUsuario([], [$sucursal], 'Administrador');
        $adminOtro = $this->createUsuario([], [$this->createSucursal('Sucursal B')], 'Administrador');
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);
        $payload = $this->correccion($venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '24.00')]);

        $this->actingAs($adminOtro)->put(route('ventas.corregir.update', $venta), $payload)->assertForbidden();
        $this->actingAs($admin)->put(route('ventas.corregir.update', $venta), $payload)
            ->assertRedirect(route('ventas.show', $venta));

        $this->assertSame('Administrador', VentaCorreccion::firstOrFail()->rol);
        $this->assertDatabaseHas('ventas', ['id_venta' => $venta->getKey(), 'total' => '24.00']);
    }

    public function test_no_permite_corregir_cuando_la_caja_ya_esta_cerrada(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo, $caja] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);
        $caja->update(['estado' => 'CERRADA']);

        $this->actingAs($cajero)->get(route('ventas.corregir', $venta))->assertForbidden();
        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '24.00')]
            ))
            ->assertForbidden();
        $this->assertSame(0, VentaCorreccion::count());
    }

    public function test_motivo_y_declaracion_son_obligatorios(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $payload = $this->correccion($venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '24.00')]);
        unset($payload['motivo'], $payload['declaracion']);

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $payload)
            ->assertSessionHasErrors(['motivo', 'declaracion']);
    }

    public function test_boton_corregir_venta_solo_aparece_cuando_corresponde(): void
    {
        [$sucursal, $cajero, $pollo, , $efectivo, $caja] = $this->escenario();
        $otro = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $this->actingAs($cajero)->get(route('ventas.show', $venta))
            ->assertOk()
            ->assertSee('Corregir venta')
            ->assertSee(route('ventas.corregir', $venta), false);

        $this->actingAs($otro)->get(route('ventas.show', $venta))
            ->assertOk()
            ->assertDontSee('Corregir venta');

        $caja->update(['estado' => 'CERRADA']);
        $this->actingAs($cajero)->get(route('ventas.show', $venta))
            ->assertOk()
            ->assertDontSee('Corregir venta');
    }

    public function test_formulario_de_correccion_solo_permite_sustituir(): void
    {
        [$sucursal, $cajero, $pollo, , $efectivo] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $this->actingAs($cajero)->get(route('ventas.corregir', $venta))
            ->assertOk()
            ->assertViewIs('ventas.corregir')
            ->assertSee('Corregir venta #'.$venta->getKey())
            ->assertSee($pollo->nombre)
            ->assertSee('Sustituir por')
            ->assertSee('Pagos registrados')
            ->assertSee('name="pagos[${index}][importe]"', false)
            ->assertSee('Volver al detalle')
            ->assertSee('Efectivo')
            ->assertSee('Motivo de la corrección')
            ->assertSee('Guardar corrección')
            ->assertDontSee('Añadir producto')
            ->assertDontSee('Añadir al detalle')
            ->assertDontSee('addLine', false)
            ->assertDontSee('removeLine', false);
    }

    public function test_no_se_eliminan_registros_historicos(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo] = $this->escenario();
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '24.00')]
            ))
            ->assertRedirect();

        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseCount('detalle_venta', 2);
        $this->assertDatabaseCount('venta_pago', 2);
        $this->assertDatabaseCount('venta_correcciones', 1);
    }

    public function test_caja_y_reportes_reflejan_la_correccion_sin_duplicar_importes(): void
    {
        [$sucursal, $cajero, $pollo, $carne, $efectivo, $caja] = $this->escenario();
        $admin = $this->createUsuario([], [$sucursal], 'Administrador');
        $venta = $this->ventaPolloX2($sucursal, $cajero, $pollo, $efectivo);

        $this->actingAs($cajero)
            ->put(route('ventas.corregir.update', $venta), $this->correccion(
                $venta, [$pollo->getKey() => $carne->getKey()], [$this->pago($efectivo, '24.00')]
            ))
            ->assertRedirect();

        $resultado = CajaCalculadora::resultado(
            $caja->fresh(),
            Venta::query()->with('pagos.metodoPago')->where('id_caja', $caja->getKey())->where('estado', 'CONFIRMADA')->get()
        );
        $this->assertSame(2400, $resultado['vendido']);
        $this->assertSame(2400, $resultado['efectivo']);

        $hoy = now()->toDateString();
        $this->actingAs($admin)->getJson('/reportes/productos-mas-vendidos?desde='.$hoy.'&hasta='.$hoy)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.cantidad', 2);

        $this->actingAs($admin)->getJson('/reportes/pagos?desde='.$hoy.'&hasta='.$hoy)
            ->assertOk()
            ->assertJsonPath('0.total', 24);

        $this->assertFalse(Route::has('ventas.anular'));
    }
}
