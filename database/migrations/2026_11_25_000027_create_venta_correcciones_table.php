<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_correcciones', function (Blueprint $table): void {
            $table->bigIncrements('id_correccion');
            $table->text('motivo');
            $table->decimal('total_anterior', 12, 2);
            $table->decimal('total_posterior', 12, 2);
            $table->jsonb('datos_anteriores');
            $table->jsonb('datos_posteriores');
            $table->timestampTz('creado_el')->useCurrent();
            $table->unsignedBigInteger('id_venta');
            $table->unsignedBigInteger('id_usuario');
            $table->string('rol', 50);
            $table->unsignedBigInteger('id_sucursal');
            $table->unsignedBigInteger('id_caja')->nullable();
            $table->foreign('id_venta')->references('id_venta')->on('ventas');
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
            $table->foreign('id_sucursal')->references('id_sucursal')->on('sucursales');
            $table->foreign('id_caja')->references('id_caja')->on('cajas');
            $table->index(['id_venta', 'creado_el'], 'venta_correcciones_venta_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_correcciones');
    }
};
