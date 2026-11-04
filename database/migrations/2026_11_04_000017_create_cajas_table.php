<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table): void {
            $table->bigIncrements('id_caja');
            $table->timestampTz('fecha_apertura')->useCurrent();
            $table->timestampTz('fecha_cierre')->nullable();
            $table->decimal('monto_inicial', 12, 2);
            $table->decimal('efectivo_contado', 12, 2)->nullable();
            $table->decimal('efectivo_esperado', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->nullable();
            $table->string('estado', 15);
            $table->timestampTz('creado_el')->useCurrent();
            $table->timestampTz('modificado_el')->nullable();
            $table->unsignedBigInteger('id_sucursal');
            $table->unsignedBigInteger('id_usuario_apertura');
            $table->unsignedBigInteger('id_usuario_cierre')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('modificado_por')->nullable();
            $table->foreign('id_sucursal')->references('id_sucursal')->on('sucursales');
            $table->foreign('id_usuario_apertura')->references('id_usuario')->on('usuarios');
            $table->foreign('id_usuario_cierre')->references('id_usuario')->on('usuarios');
            $table->index(['id_sucursal', 'fecha_apertura'], 'cajas_sucursal_apertura_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
