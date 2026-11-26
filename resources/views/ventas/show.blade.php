@extends('layouts.app')
@section('title', 'Detalle de venta #'.$venta->id_venta)
@section('content')
<div class="heading">
    <div>
        <h2>Detalle de venta #{{ $venta->id_venta }}</h2>
        <p class="muted">Información de solo lectura.</p>
    </div>
    <div class="actions">
        <a class="btn out" href="{{ route('ventas.index') }}">Volver al historial</a>
        @if($puedeCorregir)
            <a class="btn" href="{{ route('ventas.corregir', $venta) }}">Corregir venta</a>
        @endif
        <a class="btn" target="_blank" href="{{ route('ventas.receipt', $venta) }}">Imprimir comprobante</a>
    </div>
</div>

<div class="card">
    <h2>Información general</h2>
    <div class="grid grid3">
        <div>
            <label>Número de venta</label>
            <p>#{{ $venta->id_venta }}</p>
        </div>
        <div>
            <label>Fecha y hora</label>
            <p>{{ $venta->fecha_venta?->format('d/m/Y H:i') }}</p>
        </div>
        <div>
            <label>Sucursal</label>
            <p>{{ $venta->sucursal?->nombre ?: '—' }}</p>
        </div>
        <div>
            <label>Cajero responsable</label>
            <p>{{ $venta->usuario?->nombre_usuario ?: '—' }}</p>
        </div>
        <div>
            <label>Estado</label>
            <p><span class="pill {{ $venta->estado === 'CONFIRMADA' ? '' : 'off' }}">{{ $venta->estado ?: '—' }}</span></p>
        </div>
    </div>
</div>

<div class="card scroll">
    <h2>Detalle de productos</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio unitario</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($venta->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->producto?->nombre ?: '—' }}</td>
                    <td>{{ $detalle->cantidad }}</td>
                    <td>Bs {{ number_format((float) $detalle->precio_unitario, 2, ',', '.') }}</td>
                    <td>Bs {{ number_format((float) $detalle->subtotal, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">Sin productos registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card scroll">
    <h2>Métodos de pago</h2>
    <table>
        <thead>
            <tr>
                <th>Método de pago</th>
                <th>Importe pagado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($venta->pagos as $pago)
                <tr>
                    <td>{{ $pago->metodoPago?->nombre ?: '—' }}</td>
                    <td>Bs {{ number_format((float) $pago->importe, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="muted">Sin pagos registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Resumen económico</h2>
    <div class="tot">
        <span>Total de la venta</span>
        <span>Bs {{ number_format((float) $venta->total, 2, ',', '.') }}</span>
    </div>
    @if($venta->pagos->isNotEmpty())
        <div class="tot">
            <span>Total pagado</span>
            <span>Bs {{ number_format((float) $venta->pagos->sum('importe'), 2, ',', '.') }}</span>
        </div>
    @endif
</div>
@endsection
