<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['roles', 'permisos', 'sucursales', 'categorias', 'productos', 'metodos_pago'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('creado_por')->references('id_usuario')->on('usuarios');
                $table->foreign('modificado_por')->references('id_usuario')->on('usuarios');
                $table->foreign('eliminado_por')->references('id_usuario')->on('usuarios');
            });
        }

        foreach (['cajas', 'ventas'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('creado_por')->references('id_usuario')->on('usuarios');
                $table->foreign('modificado_por')->references('id_usuario')->on('usuarios');
            });
        }
    }

    public function down(): void
    {
        foreach (['roles', 'permisos', 'sucursales', 'categorias', 'productos', 'metodos_pago'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['creado_por']);
                $table->dropForeign(['modificado_por']);
                $table->dropForeign(['eliminado_por']);
            });
        }

        foreach (['cajas', 'ventas'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['creado_por']);
                $table->dropForeign(['modificado_por']);
            });
        }
    }
};
