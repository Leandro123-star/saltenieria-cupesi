<?php

namespace Database\Seeders;

use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Genera el historial operativo:
 *  - Una caja CERRADA por sucursal y día durante los últimos DIAS días,
 *    con su arqueo (efectivo y QR) calculado igual que CajaController::cerrar.
 *  - Una caja ABIERTA hoy en las sucursales Centro y Norte, con ventas del día.
 *  - Ventas CONFIRMADAS con detalle y pagos (efectivo, QR, mixto, transferencia).
 */
class CajasVentasSeeder extends Seeder
{
    private const DIAS = 30;

    private const SUCURSALES_CON_CAJA_ABIERTA = ['Sucursal Centro', 'Sucursal Norte'];

    /** Peso de cada categoría al elegir productos para una venta. */
    private const PESO_CATEGORIA = [
        'Salteñas' => 10,
        'Empanadas' => 3,
        'Bebidas' => 5,
        'Postres' => 1,
        'Combos' => 2,
    ];

    private Collection $metodos;

    public function run(): void
    {
        mt_srand(2026);

        $this->metodos = DB::table('metodos_pago')->where('activo', true)->pluck('id_metodo_pago', 'nombre');

        $sucursales = DB::table('sucursales')->where('activo', true)->orderBy('id_sucursal')->get(['id_sucursal', 'nombre']);

        foreach ($sucursales as $sucursal) {
            $cajeros = DB::table('usuario_sucursal as us')
                ->join('usuarios as u', 'u.id_usuario', '=', 'us.id_usuario')
                ->join('roles as r', 'r.id_rol', '=', 'u.id_rol')
                ->where('us.id_sucursal', $sucursal->id_sucursal)
                ->whereNull('us.fecha_fin')
                ->where('u.activo', true)
                ->where('r.nombre', 'Cajero')
                ->pluck('u.id_usuario')
                ->all();

            if ($cajeros === []) {
                continue;
            }

            $productos = DB::table('producto_sucursal as ps')
                ->join('productos as p', 'p.id_producto', '=', 'ps.id_producto')
                ->join('categorias as c', 'c.id_categoria', '=', 'p.id_categoria')
                ->where('ps.id_sucursal', $sucursal->id_sucursal)
                ->where('p.activo', true)
                ->get(['p.id_producto', 'p.precio', 'c.nombre as categoria'])
                ->map(function ($p) {
                    $p->centavos = (int) round(((float) $p->precio) * 100);
                    $p->peso = self::PESO_CATEGORIA[$p->categoria] ?? 1;
                    $p->esSaltena = $p->categoria === 'Salteñas';

                    return $p;
                })
                ->values()
                ->all();

            DB::transaction(function () use ($sucursal, $cajeros, $productos): void {
                for ($dia = self::DIAS; $dia >= 1; $dia--) {
                    // Algunos días la sucursal no abre (feriados, mantenimiento).
                    if (mt_rand(1, 100) <= 7) {
                        continue;
                    }

                    $fecha = today()->subDays($dia);
                    $apertura = $fecha->copy()->setTime(7, mt_rand(0, 30));
                    $cierre = $fecha->copy()->setTime(13, mt_rand(0, 59));
                    $finDeSemana = $fecha->isWeekend();
                    $cantidad = $finDeSemana ? mt_rand(25, 40) : mt_rand(12, 28);

                    $this->generarCaja($sucursal->id_sucursal, $cajeros, $productos, $apertura, $cierre, $cantidad, true);
                }

                if (in_array($sucursal->nombre, self::SUCURSALES_CON_CAJA_ABIERTA, true)) {
                    $apertura = now()->subHours(3)->subMinutes(mt_rand(0, 30));
                    $this->generarCaja($sucursal->id_sucursal, $cajeros, $productos, $apertura, now()->subMinutes(2), mt_rand(8, 15), false);
                }
            });
        }
    }

    private function generarCaja(int $idSucursal, array $cajeros, array $productos, CarbonInterface $apertura, CarbonInterface $hasta, int $cantidadVentas, bool $cerrar): void
    {
        $idCajero = $cajeros[array_rand($cajeros)];
        $montoInicial = [100, 150, 200][mt_rand(0, 2)] * 100;

        $idCaja = DB::table('cajas')->insertGetId([
            'fecha_apertura' => $apertura,
            'monto_inicial' => $this->bs($montoInicial),
            'estado' => 'ABIERTA',
            'id_sucursal' => $idSucursal,
            'id_usuario_apertura' => $idCajero,
            'creado_por' => $idCajero,
            'creado_el' => $apertura,
            'modificado_el' => $apertura,
        ], 'id_caja');

        $momentos = $this->momentos($apertura, $hasta, $cantidadVentas);
        $totalEfectivo = 0;
        $totalQr = 0;
        $detalles = [];
        $pagos = [];

        foreach ($momentos as $momento) {
            $lineas = $this->lineas($productos);
            $total = array_sum(array_column($lineas, 'subtotal'));

            $idVenta = DB::table('ventas')->insertGetId([
                'fecha_venta' => $momento,
                'total' => $this->bs($total),
                'estado' => 'CONFIRMADA',
                'id_sucursal' => $idSucursal,
                'id_usuario' => $idCajero,
                'id_caja' => $idCaja,
                'creado_por' => $idCajero,
                'creado_el' => $momento,
                'modificado_el' => $momento,
            ], 'id_venta');

            foreach ($lineas as $linea) {
                $detalles[] = [
                    'id_venta' => $idVenta,
                    'id_producto' => $linea['id_producto'],
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $this->bs($linea['unitario']),
                    'subtotal' => $this->bs($linea['subtotal']),
                ];
            }

            foreach ($this->pagos($total, $cerrar) as [$metodo, $importe, $verificado]) {
                $pagos[] = [
                    'id_venta' => $idVenta,
                    'id_metodo_pago' => $this->metodos[$metodo],
                    'importe' => $this->bs($importe),
                    'verificado' => $verificado,
                ];
                if ($metodo === 'Efectivo') {
                    $totalEfectivo += $importe;
                } elseif ($metodo === 'QR') {
                    $totalQr += $importe;
                }
            }
        }

        foreach (array_chunk($detalles, 500) as $lote) {
            DB::table('detalle_venta')->insert($lote);
        }
        foreach (array_chunk($pagos, 500) as $lote) {
            DB::table('venta_pago')->insert($lote);
        }

        if (! $cerrar) {
            return;
        }

        // Arqueo: la mayoría cuadra; algunos con faltante o sobrante.
        $efectivoEsperado = $montoInicial + $totalEfectivo;
        $azar = mt_rand(1, 100);
        $diferencia = match (true) {
            $azar <= 70 => 0,
            $azar <= 88 => -mt_rand(1, 20) * 50,
            default => mt_rand(1, 10) * 50,
        };
        $efectivoContado = max(0, $efectivoEsperado + $diferencia);
        $diferencia = $efectivoContado - $efectivoEsperado;

        $diferenciaQr = ($totalQr > 0 && mt_rand(1, 100) <= 10) ? -min($totalQr, mt_rand(1, 3) * 800) : 0;
        $qrVerificado = $totalQr + $diferenciaQr;

        DB::table('cajas')->where('id_caja', $idCaja)->update([
            'fecha_cierre' => $hasta,
            'efectivo_contado' => $this->bs($efectivoContado),
            'efectivo_esperado' => $this->bs($efectivoEsperado),
            'diferencia' => $this->bs($diferencia),
            'qr_verificado' => $this->bs($qrVerificado),
            'qr_esperado' => $this->bs($totalQr),
            'diferencia_qr' => $this->bs($diferenciaQr),
            'diferencia_total' => $this->bs($diferencia + $diferenciaQr),
            'estado' => 'CERRADA',
            'id_usuario_cierre' => $idCajero,
            'modificado_por' => $idCajero,
            'modificado_el' => $hasta,
        ]);
    }

    /** @return list<CarbonInterface> momentos ordenados dentro del turno */
    private function momentos(CarbonInterface $desde, CarbonInterface $hasta, int $cantidad): array
    {
        $inicio = $desde->getTimestamp() + 300;
        $fin = max($inicio + 60, $hasta->getTimestamp() - 60);
        $marcas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $marcas[] = mt_rand($inicio, $fin);
        }
        sort($marcas);

        return array_map(fn (int $t) => $desde->copy()->setTimestamp($t), $marcas);
    }

    private function lineas(array $productos): array
    {
        $cantidadLineas = $this->ponderado([1 => 40, 2 => 35, 3 => 18, 4 => 7]);
        $elegidos = [];

        while (count($elegidos) < min($cantidadLineas, count($productos))) {
            $producto = $this->productoAleatorio($productos);
            $elegidos[$producto->id_producto] = $producto;
        }

        return array_values(array_map(function ($producto) {
            $cantidad = $producto->esSaltena ? mt_rand(1, 8) : mt_rand(1, 3);

            return [
                'id_producto' => $producto->id_producto,
                'cantidad' => $cantidad,
                'unitario' => $producto->centavos,
                'subtotal' => $producto->centavos * $cantidad,
            ];
        }, $elegidos));
    }

    /** @return list<array{0: string, 1: int, 2: bool}> [método, importe en centavos, verificado] */
    private function pagos(int $total, bool $cajaCerrada): array
    {
        $qrVerificado = $cajaCerrada || mt_rand(0, 1) === 1;
        $azar = mt_rand(1, 100);

        if ($azar <= 55) {
            return [['Efectivo', $total, true]];
        }
        if ($azar <= 85) {
            return [['QR', $total, $qrVerificado]];
        }
        if ($azar <= 96 && $total >= 1000) {
            $efectivo = mt_rand(1, intdiv($total, 100) - 1) * 100;

            return [['Efectivo', $efectivo, true], ['QR', $total - $efectivo, $qrVerificado]];
        }
        if ($this->metodos->has('Transferencia')) {
            return [['Transferencia', $total, true]];
        }

        return [['Efectivo', $total, true]];
    }

    private function productoAleatorio(array $productos): object
    {
        $suma = array_sum(array_column($productos, 'peso'));
        $azar = mt_rand(1, $suma);
        foreach ($productos as $producto) {
            $azar -= $producto->peso;
            if ($azar <= 0) {
                return $producto;
            }
        }

        return end($productos);
    }

    private function ponderado(array $opciones): int
    {
        $azar = mt_rand(1, array_sum($opciones));
        foreach ($opciones as $valor => $peso) {
            $azar -= $peso;
            if ($azar <= 0) {
                return $valor;
            }
        }

        return array_key_first($opciones);
    }

    private function bs(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }
}
