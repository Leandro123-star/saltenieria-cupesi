<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permisos', function (Blueprint $table): void {
            $table->string('clave', 30)->nullable();
        });

        DB::table('permisos')->whereNull('clave')->update([
            'clave' => DB::raw('nombre'),
        ]);

        Schema::table('permisos', function (Blueprint $table): void {
            $table->unique('clave', 'permisos_clave_unique');
        });
    }

    public function down(): void
    {
        Schema::table('permisos', function (Blueprint $table): void {
            $table->dropUnique('permisos_clave_unique');
            $table->dropColumn('clave');
        });
    }
};
