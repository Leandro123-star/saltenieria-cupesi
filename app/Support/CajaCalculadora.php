<?php

namespace App\Support;

use App\Models\Caja;
use App\Models\Venta;

final class CajaCalculadora
{
    /** @param iterable<array{metodo: string, importe: string|int|float>|object> $pagos */
    public static function totalesPorMetodo(iterable $pagos): array
    {
        $totales = ['efectivo' => 0, 'qr' => 0, 'transferencia' => 0];
        foreach ($pagos as $pago) {
            $metodo = mb_strtolower(trim((string) (is_array($pago) ? $pago['metodo'] : $pago->metodo)));
            $importe = VentaCalculadora::aCentavos(is_array($pago) ? $pago['importe'] : $pago->importe);
            if (str_contains($metodo, 'efect')) {
                $totales['efectivo'] += $importe;
            } elseif (str_contains($metodo, 'qr')) {
                $totales['qr'] += $importe;
            } elseif (str_contains($metodo, 'transfer')) {
                $totales['transferencia'] += $importe;
            }
        }
        return $totales;
    }

    /** @param iterable<array{metodo: string, importe: string|int|float>|object> $pagos */
    public static function efectivoEsperado(string|int|float $montoInicial, iterable $pagos): int
    {
        $efectivo = VentaCalculadora::aCentavos($montoInicial);

        return $efectivo + self::totalesPorMetodo($pagos)['efectivo'];
    }

    public static function diferencia(string|int|float $efectivoContado, int $efectivoEsperado): int
    {
        return VentaCalculadora::aCentavos($efectivoContado) - $efectivoEsperado;
    }

    /**
     * Calcula el resultado del arqueo de una caja reutilizando los valores
     * históricos guardados al cierre y, si no existen, los calcula desde sus ventas.
     *
     * @param  iterable<int, Venta>  $ventas  Ventas confirmadas con pagos.metodoPago cargados.
     */
    public static function resultado(Caja $caja, iterable $ventas): array
    {
        $totales = [
            'ventas' => 0,
            'vendido' => 0,
            'efectivo' => 0,
            'qr' => 0,
            'transferencia' => 0,
        ];

        foreach ($ventas as $venta) {
            $totales['ventas']++;
            $totales['vendido'] += VentaCalculadora::aCentavos($venta->total);
            $porMetodo = self::totalesPorMetodo($venta->pagos->map(fn ($pago) => [
                'metodo' => $pago->metodoPago?->nombre,
                'importe' => $pago->importe,
            ]));
            $totales['efectivo'] += $porMetodo['efectivo'];
            $totales['qr'] += $porMetodo['qr'];
            $totales['transferencia'] += $porMetodo['transferencia'];
        }

        $efectivoEsperado = $caja->efectivo_esperado !== null
            ? VentaCalculadora::aCentavos($caja->efectivo_esperado)
            : VentaCalculadora::aCentavos($caja->monto_inicial) + $totales['efectivo'];
        $qrEsperado = $caja->qr_esperado !== null ? VentaCalculadora::aCentavos($caja->qr_esperado) : $totales['qr'];
        $diferencia = $caja->efectivo_contado === null
            ? null
            : ($caja->diferencia !== null
                ? VentaCalculadora::aCentavosFirmados($caja->diferencia)
                : VentaCalculadora::aCentavos($caja->efectivo_contado) - $efectivoEsperado);
        $diferenciaQr = $caja->qr_verificado === null
            ? null
            : ($caja->diferencia_qr !== null
                ? VentaCalculadora::aCentavosFirmados($caja->diferencia_qr)
                : VentaCalculadora::aCentavos($caja->qr_verificado) - $qrEsperado);

        return [
            'ventas' => $totales['ventas'],
            'vendido' => $totales['vendido'],
            'efectivo' => $totales['efectivo'],
            'qr' => $totales['qr'],
            'transferencia' => $totales['transferencia'],
            'efectivo_esperado' => $efectivoEsperado,
            'qr_esperado' => $qrEsperado,
            'qr_registrado' => ! ($caja->estado === 'CERRADA' && $caja->qr_esperado === null && $caja->qr_verificado === null),
            'qr_verificado' => $caja->qr_verificado === null ? null : VentaCalculadora::aCentavos($caja->qr_verificado),
            'diferencia' => $diferencia,
            'diferencia_efectivo' => $diferencia,
            'diferencia_qr' => $diferenciaQr,
            'diferencia_total' => $caja->diferencia_total !== null
                ? VentaCalculadora::aCentavosFirmados($caja->diferencia_total)
                : ($diferencia === null || $diferenciaQr === null ? null : $diferencia + $diferenciaQr),
            'clasificacion' => $diferencia === null ? null : match (true) {
                $diferencia < 0 => 'Faltante',
                $diferencia > 0 => 'Sobrante',
                default => 'Caja cuadrada',
            },
        ];
    }

    /**
     * Calcula el arqueo de varias cajas cargando sus ventas en una sola consulta.
     *
     * @param  iterable<int, Caja>  $cajas
     * @return array<int, array<string, mixed>>
     */
    public static function resultados(iterable $cajas): array
    {
        $cajas = collect($cajas);

        if ($cajas->isEmpty()) {
            return [];
        }

        $ventas = Venta::query()
            ->with('pagos.metodoPago')
            ->whereIn('id_caja', $cajas->pluck('id_caja'))
            ->where('estado', 'CONFIRMADA')
            ->get()
            ->groupBy('id_caja');

        return $cajas->mapWithKeys(fn (Caja $caja) => [
            $caja->getKey() => self::resultado($caja, $ventas->get($caja->getKey(), collect())),
        ])->all();
    }
}
