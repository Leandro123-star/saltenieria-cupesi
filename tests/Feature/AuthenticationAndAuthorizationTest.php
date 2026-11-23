<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\Usuario;
use Tests\TestCase;

class AuthenticationAndAuthorizationTest extends TestCase
{
    public function test_inicia_sesion_con_credenciales_validas_y_regenera_la_sesion(): void
    {
        $usuario = $this->createUsuario(['consultar_inventario']);

        $response = $this->withSession(['session_marker' => 'old'])
            ->postJson('/login', ['nombre_usuario' => $usuario->nombre_usuario, 'password' => 'password123']);

        $response->assertOk()->assertJson(['message' => 'Sesión iniciada.'])
            ->assertSessionHas('session_marker', 'old');
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_rechaza_contrasena_incorrecta_y_usuario_inactivo(): void
    {
        $usuario = $this->createUsuario();

        $this->postJson('/login', [
            'nombre_usuario' => $usuario->nombre_usuario, 'password' => 'incorrecta',
        ])->assertStatus(422);

        $usuario->update(['activo' => false]);
        $this->postJson('/login', [
            'nombre_usuario' => $usuario->nombre_usuario, 'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_protege_rutas_privadas_y_restringe_permisos(): void
    {
        $sucursal = $this->createSucursal();
        $cajero = $this->createUsuario(['registrar_ventas'], [$sucursal]);

        $this->getJson('/inventario')->assertUnauthorized();
        $this->actingAs($cajero)->getJson('/usuarios')->assertForbidden();
        $this->actingAs($cajero)->getJson('/ventas')->assertOk();
    }

    public function test_usuario_no_puede_operar_en_una_sucursal_no_asignada(): void
    {
        $asignada = $this->createSucursal();
        $noAsignada = $this->createSucursal();
        $cajero = $this->createUsuario(['gestionar_caja'], [$asignada]);

        $this->actingAs($cajero)->postJson('/cajas/abrir', [
            'id_sucursal' => $noAsignada->getKey(), 'monto_inicial' => '100.00',
        ])->assertForbidden();
    }

    public function test_cierra_sesion(): void
    {
        $usuario = $this->createUsuario();
        $this->actingAs($usuario)->post('/logout')->assertOk();
        $this->assertGuest();
    }

    public function test_administrador_crea_usuario_activo_con_roles_y_sucursal(): void
    {
        $sucursal = $this->createSucursal();
        $admin = $this->createUsuario(['gestionar_usuarios'], [], 'Administrador');
        $administrador = Rol::firstOrCreate(['nombre' => 'Administrador'], ['descripcion' => 'Administrador', 'activo' => true]);
        $cajero = Rol::firstOrCreate(['nombre' => 'Cajero'], ['descripcion' => 'Cajero', 'activo' => true]);
        $personalAdmin = Personal::create(['nombres' => 'Admin', 'apellidos' => 'Nuevo', 'activo' => true]);
        $personalCajero = Personal::create(['nombres' => 'Cajero', 'apellidos' => 'Nuevo', 'activo' => true]);

        $adminResponse = $this->actingAs($admin)->postJson('/usuarios', [
            'nombre_usuario' => 'admin_nuevo',
            'password' => 'password123',
            'id_personal' => $personalAdmin->getKey(),
            'roles' => [$administrador->getKey()],
        ]);
        $adminResponse->assertCreated()->assertJsonPath('activo', true);

        $cajeroResponse = $this->actingAs($admin)->postJson('/usuarios', [
            'nombre_usuario' => 'cajero_nuevo',
            'password' => 'password123',
            'id_personal' => $personalCajero->getKey(),
            'roles' => [$cajero->getKey()],
            'sucursales' => [['id_sucursal' => $sucursal->getKey()]],
        ]);
        $cajeroResponse->assertCreated()->assertJsonPath('activo', true);

        $nuevoCajero = Usuario::where('nombre_usuario', 'cajero_nuevo')->firstOrFail();
        $this->assertTrue($nuevoCajero->activo);
        $this->assertTrue($nuevoCajero->roles->contains($cajero));
        $this->assertTrue($nuevoCajero->sucursales->contains($sucursal));

        $this->postJson('/login', [
            'nombre_usuario' => 'cajero_nuevo',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_no_crea_usuario_si_falla_la_validacion_de_cajero(): void
    {
        $admin = $this->createUsuario(['gestionar_usuarios'], [], 'Administrador');
        $cajero = Rol::firstOrCreate(['nombre' => 'Cajero'], ['descripcion' => 'Cajero', 'activo' => true]);

        $this->actingAs($admin)->postJson('/usuarios', [
            'nombre_usuario' => 'cajero_incompleto',
            'password' => 'password123',
            'roles' => [$cajero->getKey()],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('usuarios', ['nombre_usuario' => 'cajero_incompleto']);
    }
}
