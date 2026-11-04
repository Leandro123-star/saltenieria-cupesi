<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_pago', function (Blueprint $table): void {
            $table->bigIncrements('id_venta_pago');
            $table->decimal('importe', 12, 2);
            $table->boolean('verificado');
            $table->unsignedBigInteger('id_venta');
            $table->unsignedBigInteger('id_metodo_pago');
            $table->foreign('id_venta')->references('id_venta')->on('ventas');
            $table->foreign('id_metodo_pago')->references('id_metodo_pago')->on('metodos_pago');
            $table->index('id_venta', 'venta_pago_venta_index');
            $table->index('id_metodo_pago', 'venta_pago_metodo_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_pago');
    }
};
