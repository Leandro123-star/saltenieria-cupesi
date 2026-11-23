<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_muestra_alertas_de_stock_bajo_de_la_sucursal_activa(): void
    {
        $sucursal = $this->createSucursal('Sucursal activa');
        $otraSucursal = $this->createSucursal('Otra sucursal');
        $categoria = $this->createCategoria();
        $productoActivo = $this->createProducto($categoria, ['nombre' => 'Salteña de pollo']);
        $productoDeOtraSucursal = $this->createProducto($categoria, ['nombre' => 'Salteña de otra sucursal']);
        $usuario = $this->createUsuario(['visualizar_panel'], [$sucursal]);

        $sucursal->productos()->attach($productoActivo, ['stock_actual' => 1, 'stock_minimo' => 3]);
        $otraSucursal->productos()->attach($productoDeOtraSucursal, ['stock_actual' => 0, 'stock_minimo' => 2]);

        $response = $this->actingAs($usuario)
            ->withSession(['sucursal_activa_id' => $sucursal->getKey()])
            ->get('/dashboard');

        $response->assertOk()
            ->assertSee('Salteña de pollo')
            ->assertDontSee('Salteña de otra sucursal')
            ->assertSee('1 disponibles');
    }

    public function test_muestra_estado_sin_alertas_cuando_no_hay_stock_bajo(): void
    {
        $sucursal = $this->createSucursal();
        $categoria = $this->createCategoria();
        $producto = $this->createProducto($categoria);
        $usuario = $this->createUsuario(['visualizar_panel'], [$sucursal]);

        $sucursal->productos()->attach($producto, ['stock_actual' => 10, 'stock_minimo' => 2]);

        $response = $this->actingAs($usuario)
            ->withSession(['sucursal_activa_id' => $sucursal->getKey()])
            ->get('/dashboard');

        $response->assertOk()
            ->assertSee('No hay alertas.')
            ->assertDontSee('disponibles');
    }

    public function test_conserva_indicadores_de_ventas_del_dia_y_por_sucursal(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['visualizar_panel'], [$sucursal]);
        $ahora = now();
        $idCaja = DB::table('cajas')->insertGetId([
            'fecha_apertura' => $ahora,
            'monto_inicial' => 100,
            'estado' => 'ABIERTA',
            'creado_el' => $ahora,
            'id_sucursal' => $sucursal->getKey(),
            'id_usuario_apertura' => $usuario->getKey(),
        ], 'id_caja');
        DB::table('ventas')->insert([
            'fecha_venta' => $ahora,
            'total' => 25.50,
            'estado' => 'PAGADA',
            'creado_el' => $ahora,
            'id_sucursal' => $sucursal->getKey(),
            'id_usuario' => $usuario->getKey(),
            'id_caja' => $idCaja,
        ]);

        $response = $this->actingAs($usuario)
            ->withSession(['sucursal_activa_id' => $sucursal->getKey()])
            ->getJson('/dashboard');

        $response->assertOk()
            ->assertJsonPath('resumen.cantidad_ventas', 1)
            ->assertJsonPath('resumen.ingresos', '25.50')
            ->assertJsonPath('ventas_por_sucursal.0.nombre', $sucursal->nombre)
            ->assertJsonPath('ventas_por_sucursal.0.cantidad', 1)
            ->assertJsonPath('ventas_por_sucursal.0.total', '25.50');
    }

    public function test_requiere_permiso_para_visualizar_el_dashboard(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario([], [$sucursal]);

        $this->actingAs($usuario)
            ->withSession(['sucursal_activa_id' => $sucursal->getKey()])
            ->get('/dashboard')
            ->assertForbidden();
    }
}
