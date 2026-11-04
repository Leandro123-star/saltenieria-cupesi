<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal', function (Blueprint $table): void {
            $table->bigIncrements('id_personal');
            $table->string('nombres', 30);
            $table->string('apellidos', 30);
            $table->string('ci', 20)->nullable()->unique();
            $table->string('telefono', 15)->nullable();
            $table->boolean('activo')->default(true);
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
        Schema::dropIfExists('personal');
    }
};
