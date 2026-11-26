<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Carga datos de prueba completos del sistema Cupesí.
 *
 * Pensado para una base vacía:  php artisan migrate:fresh --seed
 *
 * Todos los usuarios de prueba usan la contraseña "password".
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            SeguridadSeeder::class,       // permisos, roles y rol_permiso
            UsuariosSeeder::class,        // personas, usuarios y administrador
            SucursalesSeeder::class,      // sucursales y asignación usuario_sucursal
            CatalogoSeeder::class,        // categorías, métodos de pago, productos y stock
            CajasVentasSeeder::class,     // cajas históricas cerradas, cajas abiertas, ventas y pagos
            VentaCorreccionesSeeder::class, // correcciones de ventas en cajas abiertas
        ]);
    }
}
