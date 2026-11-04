<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal', function (Blueprint $table): void {
            $table->foreign('creado_por')
                ->references('id_usuario')
                ->on('usuarios');
            $table->foreign('modificado_por')
                ->references('id_usuario')
                ->on('usuarios');
            $table->foreign('eliminado_por')
                ->references('id_usuario')
                ->on('usuarios');
        });

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->foreign('creado_por')
                ->references('id_usuario')
                ->on('usuarios');
            $table->foreign('modificado_por')
                ->references('id_usuario')
                ->on('usuarios');
            $table->foreign('eliminado_por')
                ->references('id_usuario')
                ->on('usuarios');
        });
    }

    public function down(): void
    {
        Schema::table('personal', function (Blueprint $table): void {
            $table->dropForeign(['creado_por']);
            $table->dropForeign(['modificado_por']);
            $table->dropForeign(['eliminado_por']);
        });

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropForeign(['creado_por']);
            $table->dropForeign(['modificado_por']);
            $table->dropForeign(['eliminado_por']);
        });
    }
};
