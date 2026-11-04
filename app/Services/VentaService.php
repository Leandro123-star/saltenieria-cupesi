<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaService
{
    public function registrar(array $data, int $usuarioId): Venta
    {
        return DB::transaction(function () use ($data, $usuarioId): Venta {
            $this->authorizeSucursal($data['id_sucursal'], $usuarioId);
            $caja = Caja::query()
                ->where('id_sucursal', $data['id_sucursal'])
                ->where('estado', 'ABIERTA')
                ->lockForUpdate()
                ->first();
            if (! $caja) {
                throw ValidationException::withMessages(['id_caja' => 'No existe una caja abierta para la sucursal.']);
            }

            $totalCentavos = 0;
            $detalles = [];
            foreach ($data['detalles'] as $item) {
                $producto = Producto::query()->whereKey($item['id_producto'])->first();
                if (! $producto || ! $producto->activo) {
                    throw ValidationException::withMessages(['detalles' => 'Todos los productos deben estar activos.']);
                }
                $stock = DB::table('producto_sucursal')
                    ->where('id_producto', $producto->getKey())
                    ->where('id_sucursal', $data['id_sucursal'])
                    ->lockForUpdate()
                    ->first();
                if (! $stock || $stock->stock_actual < $item['cantidad']) {
                    throw ValidationException::withMessages(['detalles' => "Stock insuficiente para {$producto->nombre}."]);
                }
                $unitCentavos = (int) round(((float) $producto->precio) * 100);
                $subtotalCentavos = $unitCentavos * (int) $item['cantidad'];
                $totalCentavos += $subtotalCentavos;
                $detalles[] = compact('producto', 'stock', 'item', 'unitCentavos', 'subtotalCentavos');
            }

            $pagosCentavos = array_sum(array_map(
                fn (array $pago): int => (int) round(((float) $pago['importe']) * 100),
                $data['pagos']
            ));
            if ($pagosCentavos !== $totalCentavos) {
                throw ValidationException::withMessages(['pagos' => 'La suma de los pagos debe coincidir con el total.']);
            }

            $venta = Venta::create([
                'fecha_venta' => now(),
                'total' => number_format($totalCentavos / 100, 2, '.', ''),
                'estado' => 'CONFIRMADA',
                'id_sucursal' => $data['id_sucursal'],
                'id_usuario' => $usuarioId,
                'id_caja' => $caja->getKey(),
                'creado_por' => $usuarioId,
            ]);

            foreach ($detalles as $detalle) {
                $venta->detalles()->create([
                    'cantidad' => $detalle['item']['cantidad'],
                    'precio_unitario' => number_format($detalle['unitCentavos'] / 100, 2, '.', ''),
                    'subtotal' => number_format($detalle['subtotalCentavos'] / 100, 2, '.', ''),
                    'id_producto' => $detalle['producto']->getKey(),
                ]);
                DB::table('producto_sucursal')
                    ->where('id_producto', $detalle['producto']->getKey())
                    ->where('id_sucursal', $data['id_sucursal'])
                    ->decrement('stock_actual', $detalle['item']['cantidad']);
            }

            foreach ($data['pagos'] as $pago) {
                $metodo = DB::table('metodos_pago')
                    ->where('id_metodo_pago', $pago['id_metodo_pago'])
                    ->first();
                if (! $metodo || ! $metodo->activo) {
                    throw ValidationException::withMessages(['pagos' => 'Todos los métodos de pago deben estar activos.']);
                }
            }

            foreach ($data['pagos'] as $pago) {
                $venta->pagos()->create([
                    'importe' => number_format(((float) $pago['importe']), 2, '.', ''),
                    'verificado' => $pago['verificado'] ?? false,
                    'id_metodo_pago' => $pago['id_metodo_pago'],
                ]);
            }

            return $venta->load(['detalles.producto', 'pagos.metodoPago', 'caja']);
        });
    }

    private function authorizeSucursal(int $idSucursal, int $usuarioId): void
    {
        $isAdmin = DB::table('usuario_rol as ur')
            ->join('roles as r', 'r.id_rol', '=', 'ur.id_rol')
            ->where('ur.id_usuario', $usuarioId)
            ->where('r.nombre', 'Administrador')
            ->exists();
        $assigned = DB::table('usuario_sucursal')
            ->where('id_usuario', $usuarioId)
            ->where('id_sucursal', $idSucursal)
            ->exists();
        if (! $isAdmin && ! $assigned) {
            throw ValidationException::withMessages(['id_sucursal' => 'El usuario no está autorizado para esta sucursal.']);
        }
    }
}
