<?php

namespace Tests\Unit;

use App\Support\CajaCalculadora;
use App\Support\VentaCalculadora;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BusinessCalculationsTest extends TestCase
{
    public function test_calcula_subtotal_de_un_producto(): void
    {
        $this->assertSame(1250, VentaCalculadora::subtotal('12.50', 1));
    }

    public function test_calcula_total_con_varios_productos(): void
    {
        $this->assertSame(4250, VentaCalculadora::total([
            ['precio' => '12.50', 'cantidad' => 2],
            ['precio' => '17.50', 'cantidad' => 1],
        ]));
    }

    public function test_rechaza_cantidad_no_positiva(): void
    {
        $this->expectException(InvalidArgumentException::class);
        VentaCalculadora::subtotal('10.00', 0);
    }

    public function test_suma_pagos_mixtos_con_precision_de_centavos(): void
    {
        $this->assertSame(10000, VentaCalculadora::totalPagos(['33.33', '66.67']));
    }

    public function test_calcula_efectivo_esperado_sin_sumar_qr_ni_transferencia(): void
    {
        $esperado = CajaCalculadora::efectivoEsperado('50.00', [
            ['metodo' => 'Efectivo', 'importe' => '20.25'],
            ['metodo' => 'QR', 'importe' => '30.00'],
            ['metodo' => 'Transferencia bancaria', 'importe' => '10.00'],
        ]);

        $this->assertSame(7025, $esperado);
        $this->assertSame(275, CajaCalculadora::diferencia('73.00', $esperado));
    }

    public function test_rechaza_pago_no_positivo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        VentaCalculadora::totalPagos(['0.00']);
    }
}
