<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_venta', function (Blueprint $table): void {
            $table->timestampTz('reemplazado_el')->nullable()->index();
        });

        Schema::table('venta_pago', function (Blueprint $table): void {
            $table->timestampTz('reemplazado_el')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('detalle_venta', function (Blueprint $table): void {
            $table->dropColumn('reemplazado_el');
        });

        Schema::table('venta_pago', function (Blueprint $table): void {
            $table->dropColumn('reemplazado_el');
        });
    }
};
