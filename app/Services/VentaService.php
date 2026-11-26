<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaCorreccion;
use App\Support\VentaCalculadora;
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
                $unitCentavos = VentaCalculadora::aCentavos($producto->precio);
                $subtotalCentavos = VentaCalculadora::subtotal($producto->precio, (int) $item['cantidad']);
                $totalCentavos += $subtotalCentavos;
                $detalles[] = compact('producto', 'stock', 'item', 'unitCentavos', 'subtotalCentavos');
            }

            $pagosCentavos = VentaCalculadora::totalPagos(array_column($data['pagos'], 'importe'));
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

    /**
     * Corrige una venta sustituyendo productos registrados por error. No se
     * pueden añadir ni quitar líneas ni cambiar cantidades: cada línea conserva
     * su cantidad original y solo puede cambiar de producto. Las líneas y pagos
     * anteriores se marcan como reemplazados y se guarda un snapshot
     * antes/después con el detalle de cada sustitución. Todo ocurre en una sola
     * transacción con bloqueos de fila.
     */
    public function corregir(Venta $venta, array $data, Usuario $usuario): Venta
    {
        return DB::transaction(function () use ($venta, $data, $usuario): Venta {
            $venta = Venta::query()->whereKey($venta->getKey())->lockForUpdate()->firstOrFail();
            $caja = Caja::query()->whereKey($venta->id_caja)->lockForUpdate()->first();

            $this->validarCorreccion($venta, $caja, $usuario);

            $antes = $this->snapshot($venta);
            $totalAnterior = (int) round(((float) $venta->total) * 100);

            $detallesActuales = $venta->detalles()->with('producto')->get()->keyBy('id_detalle_venta');

            $sustitutos = [];
            foreach ($data['detalles'] as $item) {
                $sustitutos[(int) $item['id_detalle_venta']] = (int) $item['id_producto'];
            }

            $idsActuales = $detallesActuales->keys()->map(fn ($id) => (int) $id)->sort()->values()->all();
            $idsRecibidos = collect(array_keys($sustitutos))->sort()->values()->all();
            if ($idsActuales === [] || $idsActuales !== $idsRecibidos) {
                throw ValidationException::withMessages([
                    'detalles' => 'No se pueden añadir ni quitar productos en una corrección. Si el cliente desea más productos, registre una nueva venta.',
                ]);
            }

            $idsProductos = $detallesActuales->pluck('id_producto')
                ->merge(array_values($sustitutos))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $productos = Producto::query()
                ->whereIn('id_producto', $idsProductos)
                ->get()
                ->keyBy('id_producto');

            $filasStock = DB::table('producto_sucursal')
                ->where('id_sucursal', $venta->id_sucursal)
                ->whereIn('id_producto', $idsProductos)
                ->orderBy('id_producto')
                ->lockForUpdate()
                ->get()
                ->keyBy('id_producto');

            $stockInicial = [];
            foreach ($filasStock as $idProducto => $fila) {
                $stockInicial[(int) $idProducto] = (int) $fila->stock_actual;
            }
            $stockFinal = $stockInicial;

            // Primero se devuelve al inventario la cantidad de cada línea
            // sustituida; después se descuenta la del producto sustituto.
            foreach ($detallesActuales as $idDetalle => $detalle) {
                $idOriginal = (int) $detalle->id_producto;
                if ($sustitutos[(int) $idDetalle] !== $idOriginal && array_key_exists($idOriginal, $stockFinal)) {
                    $stockFinal[$idOriginal] += (int) $detalle->cantidad;
                }
            }

            $lineas = [];
            $sustituciones = [];
            foreach ($detallesActuales as $idDetalle => $detalle) {
                $idOriginal = (int) $detalle->id_producto;
                $idNuevo = $sustitutos[(int) $idDetalle];
                $cantidad = (int) $detalle->cantidad;

                if ($idNuevo === $idOriginal) {
                    // Línea sin cambio: conserva su precio histórico.
                    $lineas[] = [
                        'id_producto' => $idOriginal,
                        'cantidad' => $cantidad,
                        'unit_centavos' => VentaCalculadora::aCentavos($detalle->precio_unitario),
                        'subtotal_centavos' => VentaCalculadora::aCentavos($detalle->subtotal),
                    ];

                    continue;
                }

                $producto = $productos->get($idNuevo);
                if (! $producto || ! $producto->activo) {
                    throw ValidationException::withMessages(['detalles' => 'El producto sustituto debe estar activo.']);
                }
                if (! array_key_exists($idNuevo, $stockFinal)) {
                    throw ValidationException::withMessages(['detalles' => "El producto {$producto->nombre} no está disponible en la sucursal."]);
                }
                if ($stockFinal[$idNuevo] < $cantidad) {
                    throw ValidationException::withMessages(['detalles' => "Stock insuficiente para {$producto->nombre}."]);
                }
                $stockFinal[$idNuevo] -= $cantidad;

                $unitCentavos = VentaCalculadora::aCentavos($producto->precio);
                $subtotalCentavos = VentaCalculadora::subtotal($producto->precio, $cantidad);
                $lineas[] = [
                    'id_producto' => $idNuevo,
                    'cantidad' => $cantidad,
                    'unit_centavos' => $unitCentavos,
                    'subtotal_centavos' => $subtotalCentavos,
                ];
                $sustituciones[] = [
                    'id_detalle_venta' => (int) $idDetalle,
                    'producto_anterior' => ['id' => $idOriginal, 'nombre' => $detalle->producto?->nombre],
                    'producto_sustituto' => ['id' => $idNuevo, 'nombre' => $producto->nombre],
                    'cantidad' => $cantidad,
                    'precio_anterior' => number_format((float) $detalle->precio_unitario, 2, '.', ''),
                    'precio_nuevo' => number_format($unitCentavos / 100, 2, '.', ''),
                    'subtotal_anterior' => number_format((float) $detalle->subtotal, 2, '.', ''),
                    'subtotal_nuevo' => number_format($subtotalCentavos / 100, 2, '.', ''),
                ];
            }

            // Si el sustituto ya figura en otra línea de la venta, se fusionan.
            $nuevosDetalles = [];
            foreach ($lineas as $linea) {
                $id = $linea['id_producto'];
                if (! isset($nuevosDetalles[$id])) {
                    $nuevosDetalles[$id] = $linea;

                    continue;
                }
                $nuevosDetalles[$id]['cantidad'] += $linea['cantidad'];
                if ($nuevosDetalles[$id]['unit_centavos'] !== $linea['unit_centavos']) {
                    // Precio histórico distinto del vigente: la línea fusionada usa el vigente.
                    $nuevosDetalles[$id]['unit_centavos'] = VentaCalculadora::aCentavos($productos->get($id)->precio);
                }
                $nuevosDetalles[$id]['subtotal_centavos'] = $nuevosDetalles[$id]['unit_centavos'] * $nuevosDetalles[$id]['cantidad'];
            }
            $totalCentavos = array_sum(array_column($nuevosDetalles, 'subtotal_centavos'));

            if ($sustituciones === []) {
                throw ValidationException::withMessages([
                    'detalles' => 'No hay cambios que corregir. Seleccione el producto correcto en la línea registrada por error.',
                ]);
            }

            // Los pagos registrados se conservan tal cual salvo que el usuario
            // envíe un ajuste explícito porque la sustitución cambió el total.
            $pagosEnviados = $data['pagos'] ?? [];
            $ajustarPagos = $pagosEnviados !== [] && $this->pagosCambiaron($venta, $pagosEnviados);

            if ($ajustarPagos) {
                $this->validarPagos($venta, $pagosEnviados, $totalCentavos);
            } else {
                $pagadoCentavos = $venta->pagos()->get()
                    ->sum(fn ($pago): int => VentaCalculadora::aCentavos($pago->importe));
                if ($pagadoCentavos !== $totalCentavos) {
                    $diferencia = $totalCentavos - $pagadoCentavos;
                    $accion = $diferencia > 0 ? 'cobrar al cliente' : 'devolver al cliente';
                    throw ValidationException::withMessages([
                        'pagos' => sprintf(
                            'La sustitución cambia el total de Bs %s a Bs %s. Debe %s Bs %s y ajustar los pagos antes de confirmar.',
                            $this->bs($pagadoCentavos),
                            $this->bs($totalCentavos),
                            $accion,
                            $this->bs(abs($diferencia))
                        ),
                    ]);
                }
            }

            foreach ($stockFinal as $idProducto => $stock) {
                if ($stock === $stockInicial[$idProducto]) {
                    continue;
                }
                DB::table('producto_sucursal')
                    ->where('id_producto', $idProducto)
                    ->where('id_sucursal', $venta->id_sucursal)
                    ->update(['stock_actual' => $stock]);
            }

            $reemplazadoEl = now();
            $venta->detalles()->whereNull('reemplazado_el')->update(['reemplazado_el' => $reemplazadoEl]);

            foreach ($nuevosDetalles as $detalle) {
                $venta->detalles()->create([
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => number_format($detalle['unit_centavos'] / 100, 2, '.', ''),
                    'subtotal' => number_format($detalle['subtotal_centavos'] / 100, 2, '.', ''),
                    'id_producto' => $detalle['id_producto'],
                ]);
            }

            if ($ajustarPagos) {
                $venta->pagos()->whereNull('reemplazado_el')->update(['reemplazado_el' => $reemplazadoEl]);
                foreach ($pagosEnviados as $pago) {
                    $venta->pagos()->create([
                        'importe' => number_format((float) $pago['importe'], 2, '.', ''),
                        'verificado' => $pago['verificado'] ?? false,
                        'id_metodo_pago' => (int) $pago['id_metodo_pago'],
                    ]);
                }
            }

            $venta->update([
                'total' => number_format($totalCentavos / 100, 2, '.', ''),
                'modificado_por' => $usuario->getKey(),
            ]);

            $venta->refresh();
            $despues = $this->snapshot($venta);
            $despues['sustituciones'] = $sustituciones;

            VentaCorreccion::create([
                'motivo' => $data['motivo'],
                'total_anterior' => number_format($totalAnterior / 100, 2, '.', ''),
                'total_posterior' => number_format($totalCentavos / 100, 2, '.', ''),
                'datos_anteriores' => $antes,
                'datos_posteriores' => $despues,
                'id_venta' => $venta->getKey(),
                'id_usuario' => $usuario->getKey(),
                'rol' => $usuario->rol?->nombre ?? 'Sin rol',
                'id_sucursal' => $venta->id_sucursal,
                'id_caja' => $venta->id_caja,
            ]);

            return $venta->fresh(['detalles.producto', 'pagos.metodoPago', 'caja', 'usuario', 'sucursal']);
        });
    }

    private function bs(int $centavos): string
    {
        return number_format($centavos / 100, 2, ',', '.');
    }

    private function pagosCambiaron(Venta $venta, array $pagos): bool
    {
        $normalizar = fn (iterable $items): array => collect($items)
            ->map(fn ($pago): string => ((int) data_get($pago, 'id_metodo_pago')).':'
                .number_format((float) data_get($pago, 'importe'), 2, '.', ''))
            ->sort()
            ->values()
            ->all();

        return $normalizar($venta->pagos()->get()) !== $normalizar($pagos);
    }

    private function validarCorreccion(Venta $venta, ?Caja $caja, Usuario $usuario): void
    {
        if ($venta->estado !== 'CONFIRMADA') {
            throw ValidationException::withMessages(['venta' => 'Solo se pueden corregir ventas confirmadas.']);
        }

        if (! $caja || $caja->estado !== 'ABIERTA') {
            throw ValidationException::withMessages([
                'venta' => 'La caja de la venta ya está cerrada; la venta se considera entregada y no puede corregirse.',
            ]);
        }

        if (! $usuario->esAdministrador()) {
            if ((int) $venta->id_usuario !== (int) $usuario->getKey()) {
                throw ValidationException::withMessages(['venta' => 'Solo puede corregir sus propias ventas.']);
            }

            $asignada = DB::table('usuario_sucursal')
                ->where('id_usuario', $usuario->getKey())
                ->where('id_sucursal', $venta->id_sucursal)
                ->exists();

            if (! $asignada) {
                throw ValidationException::withMessages(['venta' => 'El usuario no está autorizado para esta sucursal.']);
            }
        }
    }

    private function validarPagos(Venta $venta, array $pagos, int $totalCentavos): void
    {
        try {
            $pagosCentavos = VentaCalculadora::totalPagos(array_column($pagos, 'importe'));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['pagos' => $exception->getMessage()]);
        }

        if ($pagosCentavos !== $totalCentavos) {
            throw ValidationException::withMessages(['pagos' => 'La suma de los pagos debe coincidir con el total.']);
        }

        $idsMetodos = collect($pagos)->pluck('id_metodo_pago')->map(fn ($id) => (int) $id)->unique()->all();
        $metodos = DB::table('metodos_pago')->whereIn('id_metodo_pago', $idsMetodos)->get()->keyBy('id_metodo_pago');
        $metodosAnteriores = $venta->pagos()->pluck('id_metodo_pago')->map(fn ($id) => (int) $id)->all();

        foreach ($pagos as $pago) {
            $idMetodo = (int) $pago['id_metodo_pago'];
            $metodo = $metodos->get($idMetodo);

            if (! $metodo) {
                throw ValidationException::withMessages(['pagos' => 'El método de pago seleccionado no existe.']);
            }
            if (! $metodo->activo && ! in_array($idMetodo, $metodosAnteriores, true)) {
                throw ValidationException::withMessages(['pagos' => 'Todos los métodos de pago deben estar activos.']);
            }
        }
    }

    private function snapshot(Venta $venta): array
    {
        $venta->load(['detalles.producto', 'pagos.metodoPago']);

        return [
            'venta' => [
                'id_venta' => $venta->getKey(),
                'fecha_venta' => $venta->fecha_venta?->toIso8601String(),
                'total' => (string) $venta->total,
                'estado' => $venta->estado,
                'id_sucursal' => $venta->id_sucursal,
                'id_usuario' => $venta->id_usuario,
                'id_caja' => $venta->id_caja,
            ],
            'detalles' => $venta->detalles->map(fn ($detalle): array => [
                'id_detalle_venta' => $detalle->getKey(),
                'id_producto' => $detalle->id_producto,
                'producto' => $detalle->producto?->nombre,
                'cantidad' => (int) $detalle->cantidad,
                'precio_unitario' => (string) $detalle->precio_unitario,
                'subtotal' => (string) $detalle->subtotal,
            ])->values()->all(),
            'pagos' => $venta->pagos->map(fn ($pago): array => [
                'id_venta_pago' => $pago->getKey(),
                'id_metodo_pago' => $pago->id_metodo_pago,
                'metodo' => $pago->metodoPago?->nombre,
                'importe' => (string) $pago->importe,
                'verificado' => (bool) $pago->verificado,
            ])->values()->all(),
        ];
    }

    private function authorizeSucursal(int $idSucursal, int $usuarioId): void
    {
        $isAdmin = DB::table('usuarios as u')
            ->join('roles as r', 'r.id_rol', '=', 'u.id_rol')
            ->where('u.id_usuario', $usuarioId)
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
