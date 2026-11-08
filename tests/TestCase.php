<?php

namespace Tests;

use App\Models\Categoria;
use App\Models\MetodoPago;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function createSucursal(?string $nombre = null): Sucursal
    {
        return Sucursal::create([
            'nombre' => $nombre ?? 'Sucursal '.Str::random(8),
            'direccion' => 'Dirección de prueba',
            'telefono' => '70000000',
            'activo' => true,
        ]);
    }

    protected function createCategoria(?string $nombre = null): Categoria
    {
        return Categoria::create([
            'nombre' => $nombre ?? 'Categoría '.Str::random(8),
            'descripcion' => 'Categoría de prueba',
            'activo' => true,
        ]);
    }

    protected function createProducto(Categoria $categoria, array $attributes = []): Producto
    {
        return Producto::create(array_merge([
            'nombre' => 'Producto '.Str::random(8),
            'descripcion' => 'Producto de prueba',
            'precio' => '10.00',
            'activo' => true,
            'id_categoria' => $categoria->getKey(),
        ], $attributes));
    }

    protected function createMetodoPago(string $nombre): MetodoPago
    {
        return MetodoPago::create(['nombre' => $nombre, 'activo' => true]);
    }

    protected function createUsuario(array $permissions = [], array $sucursales = [], string $roleName = 'Cajero/Vendedor'): Usuario
    {
        $role = Rol::firstOrCreate(
            ['nombre' => $roleName],
            ['descripcion' => 'Rol de prueba', 'activo' => true]
        );

        $permissionIds = collect($permissions)->map(function (string $permission): int {
            return Permiso::firstOrCreate(
                ['nombre' => $permission],
                ['descripcion' => 'Permiso de prueba']
            )->getKey();
        })->all();
        $role->permisos()->syncWithoutDetaching($permissionIds);

        $usuario = Usuario::create([
            'nombre_usuario' => 'usuario_'.Str::lower(Str::random(10)),
            'password_hash' => Hash::make('password123'),
            'activo' => true,
        ]);
        $usuario->roles()->attach($role);
        if ($sucursales !== []) {
            $usuario->sucursales()->attach(collect($sucursales)->mapWithKeys(fn (Sucursal $sucursal) => [
                $sucursal->getKey() => ['fecha_asignacion' => now()->toDateString()],
            ])->all());
        }

        return $usuario;
    }
}
