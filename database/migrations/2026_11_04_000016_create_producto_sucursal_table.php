<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_sucursal', function (Blueprint $table): void {
            $table->integer('stock_actual');
            $table->integer('stock_minimo');
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_sucursal');
            $table->primary(['id_producto', 'id_sucursal']);
            $table->foreign('id_producto')->references('id_producto')->on('productos');
            $table->foreign('id_sucursal')->references('id_sucursal')->on('sucursales');
            $table->index('id_sucursal', 'producto_sucursal_sucursal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_sucursal');
    }
};
