<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE productos ADD CONSTRAINT productos_precio_positivo CHECK (precio > 0)");
        DB::statement("ALTER TABLE producto_sucursal ADD CONSTRAINT producto_sucursal_stock_actual_no_negativo CHECK (stock_actual >= 0)");
        DB::statement("ALTER TABLE producto_sucursal ADD CONSTRAINT producto_sucursal_stock_minimo_no_negativo CHECK (stock_minimo >= 0)");
        DB::statement("ALTER TABLE cajas ADD CONSTRAINT cajas_monto_inicial_no_negativo CHECK (monto_inicial >= 0)");
        DB::statement("ALTER TABLE cajas ADD CONSTRAINT cajas_efectivo_contado_no_negativo CHECK (efectivo_contado IS NULL OR efectivo_contado >= 0)");
        DB::statement("ALTER TABLE cajas ADD CONSTRAINT cajas_estado_valido CHECK (estado IN ('ABIERTA', 'CERRADA'))");
        DB::statement("ALTER TABLE ventas ADD CONSTRAINT ventas_total_no_negativo CHECK (total >= 0)");
        DB::statement("ALTER TABLE detalle_venta ADD CONSTRAINT detalle_venta_cantidad_positiva CHECK (cantidad > 0)");
        DB::statement("ALTER TABLE detalle_venta ADD CONSTRAINT detalle_venta_precio_positivo CHECK (precio_unitario > 0)");
        DB::statement("ALTER TABLE detalle_venta ADD CONSTRAINT detalle_venta_subtotal_no_negativo CHECK (subtotal >= 0)");
        DB::statement("ALTER TABLE venta_pago ADD CONSTRAINT venta_pago_importe_positivo CHECK (importe > 0)");
        DB::statement("CREATE UNIQUE INDEX cajas_una_abierta_por_sucursal ON cajas (id_sucursal) WHERE estado = 'ABIERTA'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cajas_una_abierta_por_sucursal');
        DB::statement('ALTER TABLE venta_pago DROP CONSTRAINT IF EXISTS venta_pago_importe_positivo');
        DB::statement('ALTER TABLE detalle_venta DROP CONSTRAINT IF EXISTS detalle_venta_subtotal_no_negativo');
        DB::statement('ALTER TABLE detalle_venta DROP CONSTRAINT IF EXISTS detalle_venta_precio_positivo');
        DB::statement('ALTER TABLE detalle_venta DROP CONSTRAINT IF EXISTS detalle_venta_cantidad_positiva');
        DB::statement('ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_total_no_negativo');
        DB::statement('ALTER TABLE cajas DROP CONSTRAINT IF EXISTS cajas_estado_valido');
        DB::statement('ALTER TABLE cajas DROP CONSTRAINT IF EXISTS cajas_efectivo_contado_no_negativo');
        DB::statement('ALTER TABLE cajas DROP CONSTRAINT IF EXISTS cajas_monto_inicial_no_negativo');
        DB::statement('ALTER TABLE producto_sucursal DROP CONSTRAINT IF EXISTS producto_sucursal_stock_minimo_no_negativo');
        DB::statement('ALTER TABLE producto_sucursal DROP CONSTRAINT IF EXISTS producto_sucursal_stock_actual_no_negativo');
        DB::statement('ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_precio_positivo');
    }
};
