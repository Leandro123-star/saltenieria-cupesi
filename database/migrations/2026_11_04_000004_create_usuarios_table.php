<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->bigIncrements('id_usuario');
            $table->string('nombre_usuario', 30)->unique();
            $table->string('password_hash', 255);
            $table->boolean('activo');
            $table->timestampTz('creado_el')->useCurrent();
            $table->timestampTz('modificado_el')->nullable();
            $table->timestampTz('eliminado_el')->nullable();
            $table->unsignedBigInteger('id_personal')->nullable()->unique();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('modificado_por')->nullable();
            $table->unsignedBigInteger('eliminado_por')->nullable();

            $table->foreign('id_personal')
                ->references('id_personal')
                ->on('personal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
