<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_venta', function (Blueprint $table): void {
            $table->bigIncrements('id_detalle_venta');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 12, 2);
            $table->unsignedBigInteger('id_venta');
            $table->unsignedBigInteger('id_producto');
            $table->foreign('id_venta')->references('id_venta')->on('ventas');
            $table->foreign('id_producto')->references('id_producto')->on('productos');
            $table->index('id_venta', 'detalle_venta_venta_index');
            $table->index('id_producto', 'detalle_venta_producto_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_venta');
    }
};
