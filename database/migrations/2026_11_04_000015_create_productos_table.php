<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table): void {
            $table->bigIncrements('id_producto');
            $table->string('nombre', 50);
            $table->string('descripcion', 100)->nullable();
            $table->decimal('precio', 10, 2);
            $table->boolean('activo');
            $table->timestampTz('creado_el')->useCurrent();
            $table->timestampTz('modificado_el')->nullable();
            $table->timestampTz('eliminado_el')->nullable();
            $table->unsignedBigInteger('id_categoria');
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('modificado_por')->nullable();
            $table->unsignedBigInteger('eliminado_por')->nullable();
            $table->foreign('id_categoria')->references('id_categoria')->on('categorias');
            $table->index('id_categoria', 'productos_categoria_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
