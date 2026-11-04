<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodos_pago', function (Blueprint $table): void {
            $table->bigIncrements('id_metodo_pago');
            $table->string('nombre', 30)->unique();
            $table->boolean('activo');
            $table->timestampTz('creado_el')->useCurrent();
            $table->timestampTz('modificado_el')->nullable();
            $table->timestampTz('eliminado_el')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('modificado_por')->nullable();
            $table->unsignedBigInteger('eliminado_por')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodos_pago');
    }
};
