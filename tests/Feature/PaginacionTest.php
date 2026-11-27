<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Tests\TestCase;

class PaginacionTest extends TestCase
{
    private function admin(): Usuario
    {
        return $this->createUsuario([], [], 'Administrador');
    }

    private function crearProductos(int $cantidad): void
    {
        $categoria = $this->createCategoria('Salteñas');
        for ($i = 1; $i <= $cantidad; $i++) {
            $this->createProducto($categoria, ['nombre' => sprintf('Producto %02d', $i)]);
        }
    }

    public function test_primera_pagina_de_productos_usa_paginador_en_espanol_sin_svg(): void
    {
        $this->crearProductos(35);

        $this->actingAs($this->admin())
            ->get('/productos')
            ->assertOk()
            ->assertSee('class="pager"', false)
            ->assertSee('Mostrando 1 a 15 de 35 registros')
            ->assertSee('Página 1 de 3')
            ->assertSee('‹ Anterior')
            ->assertSee('Siguiente ›')
            ->assertSee('page=2', false)
            ->assertSee('class="product-img"', false)
            ->assertSee('Producto 01')
            ->assertDontSee('Producto 16')
            ->assertDontSee('<svg', false)
            ->assertDontSee('Previous')
            ->assertDontSee('Next')
            ->assertDontSee('Showing');
    }

    public function test_pagina_intermedia_y_ultima_de_productos(): void
    {
        $this->crearProductos(35);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/productos?page=2')
            ->assertOk()
            ->assertSee('Mostrando 16 a 30 de 35 registros')
            ->assertSee('page=1', false)
            ->assertSee('page=3', false)
            ->assertSee('<span class="on" aria-current="page">2</span>', false)
            ->assertSee('Producto 16')
            ->assertDontSee('Producto 01');

        $this->actingAs($admin)
            ->get('/productos?page=3')
            ->assertOk()
            ->assertSee('Mostrando 31 a 35 de 35 registros')
            ->assertSee('<span class="off" aria-disabled="true">Siguiente ›</span>', false)
            ->assertSee('Producto 35');
    }

    public function test_paginacion_conserva_los_filtros(): void
    {
        $this->crearProductos(35);

        $this->actingAs($this->admin())
            ->get('/productos?buscar=Producto&page=2')
            ->assertOk()
            ->assertSee('buscar=Producto', false)
            ->assertSee('Mostrando 16 a 30 de 35 registros');
    }

    public function test_una_sola_pagina_no_muestra_paginador(): void
    {
        $this->crearProductos(3);

        $this->actingAs($this->admin())
            ->get('/productos')
            ->assertOk()
            ->assertSee('Producto 01')
            ->assertDontSee('class="pager"', false);
    }

    public function test_otros_modulos_tambien_usan_el_paginador_del_sistema(): void
    {
        for ($i = 1; $i <= 17; $i++) {
            $this->createCategoria(sprintf('Categoría %02d', $i));
        }

        $this->actingAs($this->admin())
            ->get('/categorias')
            ->assertOk()
            ->assertSee('class="pager"', false)
            ->assertSee('Siguiente ›')
            ->assertDontSee('<svg', false)
            ->assertDontSee('Previous');
    }
}
