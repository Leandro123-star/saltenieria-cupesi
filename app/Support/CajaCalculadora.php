<?php

namespace App\Support;

final class CajaCalculadora
{
    /** @param iterable<array{metodo: string, importe: string|int|float>|object> $pagos */
    public static function efectivoEsperado(string|int|float $montoInicial, iterable $pagos): int
    {
        $efectivo = VentaCalculadora::aCentavos($montoInicial);

        foreach ($pagos as $pago) {
            $metodo = is_array($pago) ? $pago['metodo'] : $pago->metodo;
            $importe = is_array($pago) ? $pago['importe'] : $pago->importe;
            if (mb_strtolower($metodo) === 'efectivo') {
                $efectivo += VentaCalculadora::aCentavos($importe);
            }
        }

        return $efectivo;
    }

    public static function diferencia(string|int|float $efectivoContado, int $efectivoEsperado): int
    {
        return VentaCalculadora::aCentavos($efectivoContado) - $efectivoEsperado;
    }
}
