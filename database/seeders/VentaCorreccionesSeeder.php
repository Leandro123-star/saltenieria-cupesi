<?php

namespace Database\Seeders;

use App\Models\Usuario;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra correcciones de venta usando VentaService::corregir, de modo que
 * stock, líneas reemplazadas y snapshots queden igual que en el sistema real.
 * Solo es posible sobre ventas de cajas ABIERTAS.
 */
class VentaCorreccionesSeeder extends Seeder
{
    /** [producto registrado por error, producto correcto, motivo] */
    private const CORRECCIONES = [
        ['Salteña de carne', 'Salteña de pollo', 'El cliente pidió salteñas de pollo y se registraron de carne.'],
        ['Salteña de pollo', 'Salteña picante', 'Se marcó salteña de pollo pero el cliente llevó picantes.'],
        ['Gaseosa 500 ml', 'Agua 600 ml', 'El cliente cambió la gaseosa por agua.'],
    ];

    public function run(VentaService $servicio): void
    {
        $productos = DB::table('productos')->pluck('id_producto', 'nombre');
        $admin = Usuario::where('nombre_usuario', 'admin')->firstOrFail();
        $usadas = [];

        foreach (self::CORRECCIONES as [$errado, $correcto, $motivo]) {
            $venta = Venta::query()
                ->where('estado', 'CONFIRMADA')
                ->whereHas('caja', fn ($q) => $q->where('estado', 'ABIERTA'))
                ->whereHas('detalles', fn ($q) => $q->where('id_producto', $productos[$errado]))
                ->whereNotIn('id_venta', $usadas)
                ->with(['detalles', 'pagos'])
                ->orderBy('id_venta')
                ->first();

            if (! $venta) {
                continue;
            }

            $detalles = $venta->detalles->map(fn ($d) => [
                'id_detalle_venta' => $d->id_detalle_venta,
                'id_producto' => (int) $d->id_producto === (int) $productos[$errado] ? $productos[$correcto] : $d->id_producto,
            ])->all();

            $nuevoTotal = $venta->detalles->sum(function ($d) use ($productos, $errado, $correcto) {
                $precio = (int) $d->id_producto === (int) $productos[$errado]
                    ? (float) DB::table('productos')->where('id_producto', $productos[$correcto])->value('precio')
                    : (float) $d->precio_unitario;

                return (int) round($precio * 100) * (int) $d->cantidad;
            });

            $pagoActual = (int) round($venta->pagos->sum(fn ($p) => (float) $p->importe) * 100);
            $pagos = [];
            if ($nuevoTotal !== $pagoActual) {
                // El total cambió: se registra un único pago ajustado con el primer método usado.
                $pagos = [[
                    'id_metodo_pago' => $venta->pagos->first()->id_metodo_pago,
                    'importe' => number_format($nuevoTotal / 100, 2, '.', ''),
                    'verificado' => true,
                ]];
            }

            try {
                $servicio->corregir($venta, ['motivo' => $motivo, 'detalles' => $detalles, 'pagos' => $pagos], $admin);
                $usadas[] = $venta->id_venta;
            } catch (ValidationException $e) {
                $this->command?->warn("Corrección omitida en venta {$venta->id_venta}: ".collect($e->errors())->flatten()->first());
            }
        }
    }
}
