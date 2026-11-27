<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioPasswordUpdateTest extends TestCase
{
    private function admin(): Usuario
    {
        return $this->createUsuario([], [], 'Administrador');
    }

    private function objetivo(): Usuario
    {
        return $this->createUsuario([], [], 'Supervisor');
    }

    private function datosActuales(Usuario $usuario, array $extra = []): array
    {
        return array_merge([
            'nombre_usuario' => $usuario->nombre_usuario,
            'id_persona' => $usuario->id_persona,
            'id_rol' => $usuario->id_rol,
        ], $extra);
    }

    private function puedeIniciarSesion(Usuario $usuario, string $password): bool
    {
        Auth::logout();
        $this->flushSession();

        $status = $this->postJson('/login', [
            'nombre_usuario' => $usuario->nombre_usuario,
            'password' => $password,
        ])->status();

        Auth::logout();
        $this->flushSession();

        return $status === 200;
    }

    public function test_editar_sin_contrasena_conserva_la_anterior(): void
    {
        $usuario = $this->objetivo();
        $hashAnterior = $usuario->password_hash;

        $this->actingAs($this->admin())
            ->put('/usuarios/'.$usuario->getKey(), $this->datosActuales($usuario, ['nombre_usuario' => 'renombrado_sin_clave']))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success', 'Usuario actualizado.');

        $usuario->refresh();
        $this->assertSame('renombrado_sin_clave', $usuario->nombre_usuario);
        $this->assertSame($hashAnterior, $usuario->password_hash);
        $this->assertTrue($this->puedeIniciarSesion($usuario, 'password123'));
    }

    public function test_campos_vacios_no_modifican_la_contrasena(): void
    {
        $usuario = $this->objetivo();
        $hashAnterior = $usuario->password_hash;

        $this->actingAs($this->admin())
            ->put('/usuarios/'.$usuario->getKey(), $this->datosActuales($usuario, [
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($hashAnterior, $usuario->fresh()->password_hash);
        $this->assertTrue($this->puedeIniciarSesion($usuario, 'password123'));
    }

    public function test_cambia_la_contrasena_y_permite_iniciar_sesion_con_la_nueva(): void
    {
        $usuario = $this->objetivo();
        $hashAnterior = $usuario->password_hash;

        $this->actingAs($this->admin())
            ->put('/usuarios/'.$usuario->getKey(), $this->datosActuales($usuario, [
                'password' => 'nueva_clave_456',
                'password_confirmation' => 'nueva_clave_456',
            ]))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHasNoErrors();

        $usuario->refresh();
        $this->assertNotSame($hashAnterior, $usuario->password_hash);
        $this->assertNotSame('nueva_clave_456', $usuario->password_hash);
        $this->assertTrue(Hash::check('nueva_clave_456', $usuario->password_hash));

        $this->assertTrue($this->puedeIniciarSesion($usuario, 'nueva_clave_456'));
        $this->assertFalse($this->puedeIniciarSesion($usuario, 'password123'));
    }

    public function test_rechaza_contrasenas_que_no_coinciden(): void
    {
        $usuario = $this->objetivo();
        $hashAnterior = $usuario->password_hash;

        $this->actingAs($this->admin())
            ->from('/usuarios/'.$usuario->getKey().'/edit')
            ->put('/usuarios/'.$usuario->getKey(), $this->datosActuales($usuario, [
                'password' => 'nueva_clave_456',
                'password_confirmation' => 'otra_clave_789',
            ]))
            ->assertRedirect('/usuarios/'.$usuario->getKey().'/edit')
            ->assertSessionHasErrors(['password' => 'La confirmación no coincide con la nueva contraseña.']);

        $this->assertSame($hashAnterior, $usuario->fresh()->password_hash);
    }

    public function test_rechaza_solo_la_confirmacion(): void
    {
        $usuario = $this->objetivo();
        $hashAnterior = $usuario->password_hash;

        $this->actingAs($this->admin())
            ->put('/usuarios/'.$usuario->getKey(), $this->datosActuales($usuario, [
                'password' => '',
                'password_confirmation' => 'nueva_clave_456',
            ]))
            ->assertSessionHasErrors(['password' => 'Ingresa la nueva contraseña para poder confirmarla.']);

        $this->assertSame($hashAnterior, $usuario->fresh()->password_hash);
    }

    public function test_aplica_las_reglas_de_longitud(): void
    {
        $usuario = $this->objetivo();
        $hashAnterior = $usuario->password_hash;

        $this->actingAs($this->admin())
            ->put('/usuarios/'.$usuario->getKey(), $this->datosActuales($usuario, [
                'password' => 'corta',
                'password_confirmation' => 'corta',
            ]))
            ->assertSessionHasErrors(['password' => 'La nueva contraseña debe tener al menos 8 caracteres.']);

        $this->assertSame($hashAnterior, $usuario->fresh()->password_hash);
    }

    public function test_respuesta_json_no_expone_el_hash(): void
    {
        $usuario = $this->objetivo();

        $this->actingAs($this->admin())
            ->putJson('/usuarios/'.$usuario->getKey(), $this->datosActuales($usuario, [
                'password' => 'nueva_clave_456',
                'password_confirmation' => 'nueva_clave_456',
            ]))
            ->assertOk()
            ->assertJsonMissingPath('password_hash')
            ->assertJsonMissingPath('password');
    }

    public function test_formulario_de_edicion_muestra_la_seccion_sin_revelar_la_contrasena(): void
    {
        $usuario = $this->objetivo();

        $this->actingAs($this->admin())
            ->get('/usuarios/'.$usuario->getKey().'/edit')
            ->assertOk()
            ->assertSeeInOrder(['Persona asociada', 'Nombre de usuario', 'Rol', 'Sucursales autorizadas', 'Cambiar contraseña'])
            ->assertSee('Deja estos campos vacíos si no deseas cambiar la contraseña.')
            ->assertSee('Nueva contraseña')
            ->assertSee('Confirmar nueva contraseña')
            ->assertSee('name="password_confirmation"', false)
            ->assertDontSee($usuario->password_hash, false);
    }

    public function test_boton_editar_del_listado_abre_la_ruta_edit_con_la_seccion(): void
    {
        $usuario = $this->objetivo();
        $admin = $this->admin();
        $editUrl = route('usuarios.edit', $usuario);

        $this->actingAs($admin)
            ->get('/usuarios')
            ->assertOk()
            ->assertSee('href="'.$editUrl.'"', false);

        $this->actingAs($admin)
            ->get($editUrl)
            ->assertOk()
            ->assertSee('Cambiar contraseña')
            ->assertSee('Nueva contraseña')
            ->assertSee('Confirmar nueva contraseña');
    }

    public function test_usuario_sin_permiso_no_puede_cambiar_contrasena(): void
    {
        $usuario = $this->objetivo();
        $hashAnterior = $usuario->password_hash;
        $sinPermiso = $this->createUsuario(['registrar_ventas'], [$this->createSucursal()]);

        $this->actingAs($sinPermiso)
            ->putJson('/usuarios/'.$usuario->getKey(), $this->datosActuales($usuario, [
                'password' => 'nueva_clave_456',
                'password_confirmation' => 'nueva_clave_456',
            ]))
            ->assertForbidden();

        $this->assertSame($hashAnterior, $usuario->fresh()->password_hash);
    }
}
