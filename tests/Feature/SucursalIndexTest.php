<?php

namespace Tests\Feature;

use Tests\TestCase;

class SucursalIndexTest extends TestCase
{
    public function test_index_muestra_tabla_completa_sin_filtro_de_busqueda(): void
    {
        $this->createSucursal('Sucursal Centro');
        $this->createSucursal('Sucursal Norte')->update(['activo' => false]);
        $admin = $this->createUsuario([], [], 'Administrador');

        $response = $this->actingAs($admin)->get('/sucursales')->assertOk();

        $response->assertSee('Gestionar sucursales')
            ->assertSee('+ Nueva sucursal')
            ->assertSeeInOrder(['Nombre', 'Dirección', 'Teléfono', 'Productos', 'Ventas', 'Estado', 'Acciones'])
            ->assertSee('Sucursal Centro')
            ->assertSee('Sucursal Norte')
            ->assertSee('Editar')
            // El estado se cambia solo desde «Editar sucursal».
            ->assertDontSee('>Desactivar<', false)
            ->assertDontSee('name="_method" value="DELETE"', false)
            ->assertSee('Activa')
            ->assertSee('Inactiva')
            ->assertDontSee('name="buscar"', false)
            ->assertDontSee('Buscar sucursal')
            ->assertDontSee('Limpiar');
    }

    public function test_estado_se_cambia_desde_editar_sucursal(): void
    {
        $sucursal = $this->createSucursal('Sucursal Centro');
        $admin = $this->createUsuario([], [], 'Administrador');
        $datos = ['nombre' => 'Sucursal Centro', 'direccion' => 'Av. Principal 123', 'telefono' => '3334444'];

        $this->actingAs($admin)->get(route('sucursales.edit', $sucursal))
            ->assertOk()->assertSee('name="activo"', false);

        $this->actingAs($admin)->put(route('sucursales.update', $sucursal), $datos + ['activo' => '0'])
            ->assertRedirect(route('sucursales.index'))->assertSessionHasNoErrors();
        $this->assertFalse($sucursal->fresh()->activo);
        $this->actingAs($admin)->get(route('sucursales.index'))->assertSee('Inactiva');

        $this->actingAs($admin)->put(route('sucursales.update', $sucursal), $datos + ['activo' => '1'])
            ->assertRedirect(route('sucursales.index'))->assertSessionHasNoErrors();
        $this->assertTrue($sucursal->fresh()->activo);
    }

    public function test_parametro_buscar_ya_no_filtra_el_listado(): void
    {
        $this->createSucursal('Sucursal Centro');
        $this->createSucursal('Sucursal Norte')->update(['activo' => false]);
        $admin = $this->createUsuario([], [], 'Administrador');

        $this->actingAs($admin)
            ->get('/sucursales?buscar=xyz')
            ->assertOk()
            ->assertSee('Sucursal Centro')
            ->assertSee('Sucursal Norte');
    }
}
