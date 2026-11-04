<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_sucursal', function (Blueprint $table): void {
            $table->date('fecha_asignacion')->useCurrent();
            $table->date('fecha_fin')->nullable();
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_sucursal');
            $table->primary(['id_usuario', 'id_sucursal']);
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->cascadeOnDelete();
            $table->foreign('id_sucursal')->references('id_sucursal')->on('sucursales')->cascadeOnDelete();
            $table->index('id_sucursal', 'usuario_sucursal_sucursal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_sucursal');
    }
};
