@extends('layouts.app')
@section('title', 'Detalle de caja')
@section('content')
@php
    $money = fn (int|float|string|null $value): string => number_format(((float) $value) / 100, 2, ',', '.');
    $date = fn ($value): string => $value?->format('d/m/Y H:i') ?? '—';
    $differenceClass = fn (int|float|null $value): string => $value === null ? 'muted' : ($value < 0 ? 'pill off' : 'pill');
    $differenceLabel = fn (int|float|null $value): string => $value === null ? 'No registrado' : ($value < 0 ? 'Faltante' : ($value > 0 ? 'Sobrante' : 'Caja cuadrada'));
@endphp
<div class="heading"><div><h2>Detalle de caja #{{ $caja->id_caja }}</h2><span class="muted">Consulta histórica de solo lectura desde Reportes → Historial de caja</span></div><a class="btn out" href="{{ route('reportes.cajas') }}">Volver a Historial de caja</a></div>
<div class="grid grid2"><div class="card"><h2>Información de la caja</h2><table>
    <tr><th>Sucursal</th><td>{{ $caja->sucursal?->nombre ?? '—' }}</td></tr>
    <tr><th>Cajero responsable</th><td>{{ $caja->usuarioApertura?->personal ? trim($caja->usuarioApertura->personal->nombres.' '.$caja->usuarioApertura->personal->apellidos) : ($caja->usuarioApertura?->nombre_usuario ?? '—') }}</td></tr>
    <tr><th>Fecha de apertura</th><td>{{ $date($caja->fecha_apertura) }}</td></tr><tr><th>Fecha de cierre</th><td>{{ $date($caja->fecha_cierre) }}</td></tr>
    <tr><th>Estado</th><td><span class="pill {{ $caja->estado === 'ABIERTA' ? '' : 'off' }}">{{ $caja->estado }}</span></td></tr>
</table></div><div class="card"><h2>Ventas y cobros</h2><table>
    <tr><th>Cantidad de ventas</th><td>{{ $resultado['ventas'] }}</td></tr><tr><th>Total de ventas</th><td>Bs {{ $money($resultado['vendido']) }}</td></tr>
    <tr><th>Cobrado en efectivo</th><td>Bs {{ $money($resultado['efectivo']) }}</td></tr><tr><th>Cobrado por QR</th><td>Bs {{ $money($resultado['qr']) }}</td></tr><tr><th>Cobrado por transferencia</th><td>Bs {{ $money($resultado['transferencia']) }}</td></tr>
</table></div></div>
<div class="card"><h2>Arqueo de caja</h2><table>
    <tr><th>Monto inicial</th><td>Bs {{ number_format($caja->monto_inicial, 2, ',', '.') }}</td></tr>
    <tr><th>Efectivo esperado</th><td>Bs {{ $money($resultado['efectivo_esperado']) }}</td></tr><tr><th>Efectivo contado</th><td>{{ $caja->efectivo_contado === null ? 'Pendiente' : 'Bs '.number_format($caja->efectivo_contado, 2, ',', '.') }}</td></tr>
    <tr><th>Diferencia de efectivo</th><td>{{ $resultado['diferencia_efectivo'] === null ? 'No registrado' : 'Bs '.$money($resultado['diferencia_efectivo']) }} @if($resultado['diferencia_efectivo'] !== null)<span class="{{ $differenceClass($resultado['diferencia_efectivo']) }}">{{ $differenceLabel($resultado['diferencia_efectivo']) }}</span>@endif</td></tr>
    <tr><th>QR esperado</th><td>{{ $resultado['qr_registrado'] ? 'Bs '.$money($resultado['qr_esperado']) : 'No registrado' }}</td></tr><tr><th>QR verificado</th><td>{{ $resultado['qr_verificado'] === null ? 'No registrado' : 'Bs '.$money($resultado['qr_verificado']) }}</td></tr>
    <tr><th>Diferencia de QR</th><td>{{ $resultado['diferencia_qr'] === null ? 'No registrado' : 'Bs '.$money($resultado['diferencia_qr']) }} @if($resultado['diferencia_qr'] !== null)<span class="{{ $differenceClass($resultado['diferencia_qr']) }}">{{ $differenceLabel($resultado['diferencia_qr']) }}</span>@endif</td></tr>
    <tr><th>Diferencia total</th><td>{{ $resultado['diferencia_total'] === null ? 'No registrado' : 'Bs '.$money($resultado['diferencia_total']) }} @if($resultado['diferencia_total'] !== null)<span class="{{ $differenceClass($resultado['diferencia_total']) }}">{{ $differenceLabel($resultado['diferencia_total']) }}</span>@endif</td></tr>
</table></div>
@endsection
