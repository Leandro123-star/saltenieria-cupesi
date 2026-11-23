<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cajas', function (Blueprint $table): void {
            $table->decimal('qr_verificado', 12, 2)->nullable();
            $table->decimal('qr_esperado', 12, 2)->nullable();
            $table->decimal('diferencia_qr', 12, 2)->nullable();
            $table->decimal('diferencia_total', 12, 2)->nullable();
        });
        Schema::getConnection()->statement('ALTER TABLE cajas ADD CONSTRAINT cajas_qr_verificado_no_negativo CHECK (qr_verificado IS NULL OR qr_verificado >= 0)');
        Schema::getConnection()->statement('ALTER TABLE cajas ADD CONSTRAINT cajas_qr_esperado_no_negativo CHECK (qr_esperado IS NULL OR qr_esperado >= 0)');
    }

    public function down(): void
    {
        Schema::getConnection()->statement('ALTER TABLE cajas DROP CONSTRAINT IF EXISTS cajas_qr_esperado_no_negativo');
        Schema::getConnection()->statement('ALTER TABLE cajas DROP CONSTRAINT IF EXISTS cajas_qr_verificado_no_negativo');
        Schema::table('cajas', function (Blueprint $table): void {
            $table->dropColumn(['qr_verificado', 'qr_esperado', 'diferencia_qr', 'diferencia_total']);
        });
    }
};
