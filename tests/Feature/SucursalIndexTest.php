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
            ->assertSee('Desactivar')
            ->assertSee('Activa')
            ->assertSee('Inactiva')
            ->assertDontSee('name="buscar"', false)
            ->assertDontSee('Buscar sucursal')
            ->assertDontSee('Limpiar');
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
