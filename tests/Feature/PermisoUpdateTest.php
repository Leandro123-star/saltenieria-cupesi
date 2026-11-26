<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Tests\TestCase;

class PermisoUpdateTest extends TestCase
{
    private function admin(): Usuario
    {
        return $this->createUsuario([], [], 'Administrador');
    }

    public function test_renombra_permiso_conservando_roles_clave_y_autorizacion(): void
    {
        $sucursal = $this->createSucursal();
        $permiso = Permiso::create(['nombre' => 'gestionar_productos', 'descripcion' => 'Gestión de productos']);
        $rol = Rol::create(['nombre' => 'Bodeguero', 'descripcion' => 'Bodega', 'activo' => true]);
        $rol->permisos()->attach($permiso->getKey());
        $usuario = $this->createUsuario([], [$sucursal], 'Bodeguero');

        $this->actingAs($this->admin())
            ->putJson('/permisos/'.$permiso->getKey(), [
                'nombre' => 'consultar_productos',
                'descripcion' => 'Consulta de productos',
            ])
            ->assertOk()
            ->assertJsonPath('nombre', 'consultar_productos')
            ->assertJsonPath('descripcion', 'Consulta de productos')
            ->assertJsonPath('clave', 'gestionar_productos');

        $this->assertDatabaseHas('permisos', [
            'id_permiso' => $permiso->getKey(),
            'nombre' => 'consultar_productos',
            'clave' => 'gestionar_productos',
        ]);
        $this->assertDatabaseHas('rol_permiso', [
            'id_rol' => $rol->getKey(),
            'id_permiso' => $permiso->getKey(),
        ]);

        $this->actingAs($usuario)->getJson('/productos')->assertOk();
        $this->assertTrue($usuario->fresh()->tienePermiso('gestionar_productos'));
        $this->assertFalse($usuario->fresh()->tienePermiso('consultar_productos'));
    }

    public function test_permite_renombrar_con_espacios_y_conserva_la_autorizacion(): void
    {
        $sucursal = $this->createSucursal();
        $permiso = Permiso::create(['nombre' => 'visualizar_panel', 'descripcion' => 'Panel']);
        $rol = Rol::create(['nombre' => 'Supervisor', 'descripcion' => 'Supervisión', 'activo' => true]);
        $rol->permisos()->attach($permiso->getKey());
        $usuario = $this->createUsuario([], [$sucursal], 'Supervisor');

        $this->actingAs($this->admin())
            ->putJson('/permisos/'.$permiso->getKey(), [
                'nombre' => '  visualizar   panel  ',
                'descripcion' => 'Acceso al panel',
            ])
            ->assertOk()
            ->assertJsonPath('nombre', 'visualizar panel')
            ->assertJsonPath('descripcion', 'Acceso al panel')
            ->assertJsonPath('clave', 'visualizar_panel');

        $this->assertDatabaseHas('permisos', [
            'id_permiso' => $permiso->getKey(),
            'nombre' => 'visualizar panel',
            'clave' => 'visualizar_panel',
        ]);
        $this->assertDatabaseHas('rol_permiso', [
            'id_rol' => $rol->getKey(),
            'id_permiso' => $permiso->getKey(),
        ]);

        $this->actingAs($usuario)->getJson('/dashboard')->assertOk();
    }

    public function test_crea_permiso_con_espacios_y_normaliza_su_clave(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/permisos', [
                'nombre' => 'visualizar   panel',
                'descripcion' => 'Acceso al panel',
            ])
            ->assertCreated()
            ->assertJsonPath('nombre', 'visualizar panel')
            ->assertJsonPath('clave', 'visualizar_panel');

        $permiso = Permiso::where('clave', 'visualizar_panel')->firstOrFail();
        $this->assertSame('visualizar panel', $permiso->nombre);

        $sucursal = $this->createSucursal();
        $rol = Rol::create(['nombre' => 'Analista', 'descripcion' => 'Análisis', 'activo' => true]);
        $rol->permisos()->attach($permiso->getKey());
        $usuario = $this->createUsuario([], [$sucursal], 'Analista');

        $this->actingAs($usuario)->getJson('/dashboard')->assertOk();
    }

    public function test_rechaza_duplicados_equivalentes_al_normalizar_espacios(): void
    {
        Permiso::create(['nombre' => 'visualizar panel']);

        $this->actingAs($this->admin())
            ->postJson('/permisos', ['nombre' => 'visualizar panel'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');

        $this->actingAs($this->admin())
            ->postJson('/permisos', ['nombre' => 'visualizar_panel'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    public function test_acepta_hasta_30_caracteres_incluyendo_espacios(): void
    {
        $nombre = 'ab'.str_repeat(' c', 14);
        $this->assertSame(30, strlen($nombre));

        $this->actingAs($this->admin())
            ->postJson('/permisos', ['nombre' => $nombre])
            ->assertCreated()
            ->assertJsonPath('nombre', $nombre);

        $this->actingAs($this->admin())
            ->postJson('/permisos', ['nombre' => $nombre.' d'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    public function test_rechaza_nombres_duplicados_y_claves_de_otros_permisos(): void
    {
        $admin = $this->admin();
        $productos = Permiso::create(['nombre' => 'gestionar_productos']);
        $caja = Permiso::create(['nombre' => 'gestionar_caja']);

        $this->actingAs($admin)
            ->putJson('/permisos/'.$caja->getKey(), ['nombre' => 'gestionar_productos'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');

        $this->actingAs($admin)
            ->putJson('/permisos/'.$productos->getKey(), ['nombre' => 'consultar_productos'])
            ->assertOk();

        // Aunque "gestionar_productos" ya no es un nombre visible, sigue siendo
        // la clave interna del permiso y no puede reutilizarse en otro registro.
        $this->actingAs($admin)
            ->putJson('/permisos/'.$caja->getKey(), ['nombre' => 'gestionar_productos'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');

        // El propio permiso sí puede recuperar su nombre original.
        $this->actingAs($admin)
            ->putJson('/permisos/'.$productos->getKey(), ['nombre' => 'gestionar_productos'])
            ->assertOk();
    }

    public function test_valida_formato_longitud_y_descripcion(): void
    {
        $admin = $this->admin();
        $permiso = Permiso::create(['nombre' => 'gestionar_reportes', 'descripcion' => 'Original']);

        foreach (['Gestionar reportes', '1reportes', 'gestionar-reportes', str_repeat('a', 31), ''] as $invalido) {
            $this->actingAs($admin)
                ->putJson('/permisos/'.$permiso->getKey(), ['nombre' => $invalido])
                ->assertStatus(422)
                ->assertJsonValidationErrors('nombre');
        }

        $this->actingAs($admin)
            ->putJson('/permisos/'.$permiso->getKey(), [
                'nombre' => 'generar_reportes',
                'descripcion' => str_repeat('d', 101),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('descripcion');

        $this->actingAs($admin)
            ->putJson('/permisos/'.$permiso->getKey(), [
                'nombre' => 'generar_reportes',
                'descripcion' => 'Reportes actualizados',
                'clave' => 'clave_manipulada',
            ])
            ->assertOk();

        $this->assertDatabaseHas('permisos', [
            'id_permiso' => $permiso->getKey(),
            'nombre' => 'generar_reportes',
            'descripcion' => 'Reportes actualizados',
            'clave' => 'gestionar_reportes',
        ]);
    }

    public function test_index_muestra_la_accion_y_el_formulario_de_edicion(): void
    {
        Permiso::create(['nombre' => 'gestionar_categorias', 'descripcion' => 'Categorías']);

        $this->actingAs($this->admin())
            ->get('/permisos')
            ->assertOk()
            ->assertSee('Editar')
            ->assertSee('Guardar cambios')
            ->assertSee('permisoModal', false);
    }

    public function test_usuario_sin_permiso_no_puede_editar(): void
    {
        $usuario = $this->createUsuario(['registrar_ventas'], [$this->createSucursal()]);
        $permiso = Permiso::create(['nombre' => 'gestionar_categorias']);

        $this->actingAs($usuario)
            ->putJson('/permisos/'.$permiso->getKey(), ['nombre' => 'consultar_categorias'])
            ->assertForbidden();
    }
}
