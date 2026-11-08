<?php

namespace App\Support;

use InvalidArgumentException;

final class VentaCalculadora
{
    public static function aCentavos(string|int|float $importe): int
    {
        $valor = number_format((float) $importe, 2, '.', '');

        if ((float) $valor < 0) {
            throw new InvalidArgumentException('El importe no puede ser negativo.');
        }

        return (int) round(((float) $valor) * 100);
    }

    public static function subtotal(string|int|float $precio, int $cantidad): int
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }

        $precioCentavos = self::aCentavos($precio);

        if ($precioCentavos <= 0) {
            throw new InvalidArgumentException('El precio debe ser mayor que cero.');
        }

        return $precioCentavos * $cantidad;
    }

    /** @param array<int, array{precio: string|int|float, cantidad: int}> $detalles */
    public static function total(array $detalles): int
    {
        return array_sum(array_map(
            fn (array $detalle): int => self::subtotal($detalle['precio'], (int) $detalle['cantidad']),
            $detalles
        ));
    }

    /** @param array<int, string|int|float> $importes */
    public static function totalPagos(array $importes): int
    {
        $total = 0;

        foreach ($importes as $importe) {
            $centavos = self::aCentavos($importe);
            if ($centavos <= 0) {
                throw new InvalidArgumentException('El importe del pago debe ser mayor que cero.');
            }
            $total += $centavos;
        }

        return $total;
    }
}
