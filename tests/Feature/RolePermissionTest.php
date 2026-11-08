<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Rol;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    public function test_usuario_acumula_permisos_de_sus_roles_y_relaciones_de_sucursal(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $permiso = Permiso::where('nombre', 'registrar_ventas')->firstOrFail();

        $this->assertTrue($usuario->roles()->exists());
        $this->assertTrue($usuario->roles()->first()->permisos->contains($permiso));
        $this->assertTrue($usuario->sucursales->contains($sucursal));
        $this->assertSame($sucursal->getKey(), $usuario->sucursales->first()->pivot->id_sucursal);
    }

    public function test_administrador_puede_acceder_a_funciones_administrativas(): void
    {
        $admin = $this->createUsuario([], [], 'Administrador');

        $this->actingAs($admin)->getJson('/roles')->assertOk();
        $this->actingAs($admin)->getJson('/sucursales')->assertOk();
        $this->actingAs($admin)->getJson('/reportes/ventas-periodo')->assertOk();
    }
}
