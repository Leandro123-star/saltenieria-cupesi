<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table): void {
            $table->bigIncrements('id_venta');
            $table->timestampTz('fecha_venta')->useCurrent();
            $table->decimal('total', 12, 2);
            $table->string('estado', 15);
            $table->timestampTz('creado_el')->useCurrent();
            $table->timestampTz('modificado_el')->nullable();
            $table->unsignedBigInteger('id_sucursal');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_caja');
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('modificado_por')->nullable();
            $table->foreign('id_sucursal')->references('id_sucursal')->on('sucursales');
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
            $table->foreign('id_caja')->references('id_caja')->on('cajas');
            $table->index(['id_sucursal', 'fecha_venta'], 'ventas_sucursal_fecha_index');
            $table->index(['id_caja', 'fecha_venta'], 'ventas_caja_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
