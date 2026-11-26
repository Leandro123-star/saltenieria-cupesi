<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Usuario;
use Tests\TestCase;

class CategoriaDesactivacionTest extends TestCase
{
    private function admin(): Usuario
    {
        return $this->createUsuario(['gestionar_categorias', 'gestionar_productos'], [], 'Administrador');
    }

    public function test_desactiva_categoria_con_productos_y_los_productos_conservan_su_categoria(): void
    {
        $admin = $this->admin();
        $categoria = $this->createCategoria('Salteñas');
        $producto = $this->createProducto($categoria);

        $this->actingAs($admin)
            ->delete(route('categorias.destroy', $categoria))
            ->assertRedirect(route('categorias.index'))
            ->assertSessionHas('success', 'Categoría desactivada correctamente.');

        $categoria->refresh();
        $this->assertFalse($categoria->activo);
        $this->assertSame($admin->getKey(), $categoria->modificado_por);
        $this->assertDatabaseHas('categorias', ['id_categoria' => $categoria->getKey()]);
        $this->assertSame($categoria->getKey(), $producto->fresh()->id_categoria);
        $this->assertSame(1, $categoria->productos()->count());
    }

    public function test_desactivar_categoria_ya_inactiva_muestra_mensaje_sin_error_422(): void
    {
        $categoria = $this->createCategoria();
        $categoria->update(['activo' => false]);

        $this->actingAs($this->admin())
            ->delete(route('categorias.destroy', $categoria))
            ->assertRedirect(route('categorias.index'))
            ->assertSessionHas('error', 'La categoría ya está inactiva.');
    }

    public function test_categoria_inactiva_no_se_ofrece_ni_se_acepta_para_productos_nuevos(): void
    {
        $admin = $this->admin();
        $this->createCategoria('Bebidas activas');
        $inactiva = $this->createCategoria('Postres antiguos');
        $inactiva->update(['activo' => false]);

        $this->actingAs($admin)->get(route('productos.create'))
            ->assertOk()
            ->assertSee('Bebidas activas')
            ->assertDontSee('Postres antiguos');

        $this->actingAs($admin)
            ->from(route('productos.create'))
            ->post(route('productos.store'), [
                'nombre' => 'Producto nuevo',
                'precio' => '12.00',
                'activo' => '1',
                'id_categoria' => $inactiva->getKey(),
            ])
            ->assertRedirect(route('productos.create'))
            ->assertSessionHasErrors(['id_categoria' => 'La categoría seleccionada no está activa.']);

        $this->assertDatabaseMissing('productos', ['nombre' => 'Producto nuevo']);
    }

    public function test_editar_producto_de_categoria_inactiva_la_conserva_y_no_permite_asignar_otra_inactiva(): void
    {
        $admin = $this->admin();
        $original = $this->createCategoria('Categoría original');
        $otraInactiva = $this->createCategoria('Otra inactiva');
        $producto = $this->createProducto($original, ['nombre' => 'Salteña clásica']);
        $original->update(['activo' => false]);
        $otraInactiva->update(['activo' => false]);

        $this->actingAs($admin)->get(route('productos.edit', $producto))
            ->assertOk()
            ->assertSee('Categoría original (inactiva)')
            ->assertDontSee('Otra inactiva');

        $payload = ['nombre' => 'Salteña clásica editada', 'precio' => '15.00', 'activo' => '1'];

        $this->actingAs($admin)
            ->put(route('productos.update', $producto), $payload + ['id_categoria' => $original->getKey()])
            ->assertRedirect(route('productos.index'))
            ->assertSessionHasNoErrors();
        $this->assertSame($original->getKey(), $producto->fresh()->id_categoria);
        $this->assertSame('Salteña clásica editada', $producto->fresh()->nombre);

        $this->actingAs($admin)
            ->put(route('productos.update', $producto), $payload + ['id_categoria' => $otraInactiva->getKey()])
            ->assertSessionHasErrors('id_categoria');
        $this->assertSame($original->getKey(), $producto->fresh()->id_categoria);
    }

    public function test_activar_categoria_inactiva_con_productos_la_vuelve_a_ofrecer(): void
    {
        $admin = $this->admin();
        $categoria = $this->createCategoria('Salteñas especiales');
        $producto = $this->createProducto($categoria);

        $this->actingAs($admin)->delete(route('categorias.destroy', $categoria));
        $this->actingAs($admin)->get(route('productos.create'))->assertDontSee('Salteñas especiales');

        $this->actingAs($admin)
            ->patch(route('categorias.restore', $categoria))
            ->assertRedirect(route('categorias.index'))
            ->assertSessionHas('success', 'Categoría activada correctamente.');

        $categoria->refresh();
        $this->assertTrue($categoria->activo);
        $this->assertSame($admin->getKey(), $categoria->modificado_por);
        $this->assertSame($categoria->getKey(), $producto->fresh()->id_categoria);

        $this->actingAs($admin)->get(route('productos.create'))->assertSee('Salteñas especiales');
    }

    public function test_activar_categoria_ya_activa_muestra_mensaje_sin_error(): void
    {
        $categoria = $this->createCategoria();

        $this->actingAs($this->admin())
            ->patch(route('categorias.restore', $categoria))
            ->assertRedirect(route('categorias.index'))
            ->assertSessionHas('error', 'La categoría ya está activa.');
    }

    public function test_tabla_muestra_ambos_estados_con_su_accion_y_sin_buscador(): void
    {
        $admin = $this->admin();
        $activa = $this->createCategoria('Empanadas');
        $inactiva = $this->createCategoria('Refrescos');
        $inactiva->update(['activo' => false]);

        $html = $this->actingAs($admin)->get(route('categorias.index'))
            ->assertOk()
            ->assertSee('Empanadas')
            ->assertSee('Refrescos')
            ->assertSee('Nueva categoría')
            ->assertDontSee('Buscar por nombre')
            ->assertDontSee('Limpiar')
            ->assertDontSee('Reactivar categoría inactiva')
            ->getContent();

        $filaActiva = $this->fila($html, 'Empanadas');
        $this->assertStringContainsString('Activa<', $filaActiva);
        $this->assertStringContainsString(route('categorias.destroy', $activa), $filaActiva);
        $this->assertStringContainsString('>Desactivar<', $filaActiva);
        $this->assertStringNotContainsString('>Activar<', $filaActiva);

        $filaInactiva = $this->fila($html, 'Refrescos');
        $this->assertStringContainsString('Inactiva<', $filaInactiva);
        $this->assertStringContainsString(route('categorias.restore', $inactiva), $filaInactiva);
        $this->assertStringContainsString('>Activar<', $filaInactiva);
        $this->assertStringNotContainsString('>Desactivar<', $filaInactiva);
        $this->assertStringContainsString('>Editar<', $filaInactiva);
    }

    public function test_edicion_de_categorias_sigue_funcionando(): void
    {
        $categoria = $this->createCategoria('Empanadas');

        $this->actingAs($this->admin())
            ->put(route('categorias.update', $categoria), [
                'nombre' => 'Empanadas horneadas',
                'descripcion' => 'Editada',
                'activo' => '1',
            ])
            ->assertRedirect(route('categorias.index'))
            ->assertSessionHas('success');

        $this->assertSame('Empanadas horneadas', $categoria->fresh()->nombre);
    }

    private function fila(string $html, string $nombre): string
    {
        $this->assertMatchesRegularExpression('#<tr><td><strong>'.preg_quote($nombre, '#').'</strong>.*?</tr>#s', $html);
        preg_match('#<tr><td><strong>'.preg_quote($nombre, '#').'</strong>.*?</tr>#s', $html, $m);

        return $m[0];
    }
}
