<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acceso explícito de un Administrador a todas las sucursales activas
 * (incluidas las que se creen después). Solo agrega la columna: los usuarios
 * existentes conservan sus datos y quedan con false (acceso según sus filas
 * en usuario_sucursal). No reconstruye la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('usuarios', 'acceso_todas_sucursales')) {
            return;
        }

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->boolean('acceso_todas_sucursales')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('usuarios', 'acceso_todas_sucursales')) {
            return;
        }

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropColumn('acceso_todas_sucursales');
        });
    }
};
