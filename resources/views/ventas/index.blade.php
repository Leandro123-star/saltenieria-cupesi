@extends('layouts.app')
@section('title', 'Historial de ventas')
@section('content')
<div class="heading">
    <h2>Historial de ventas</h2>
    <a class="btn" href="{{ route('ventas.create') }}">+ Registrar venta</a>
</div>

<div class="card">
    <form method="GET" action="{{ route('ventas.index') }}" class="formgrid">
        <div class="f">
            <label for="desde">Fecha inicial</label>
            <input id="desde" type="date" name="desde" value="{{ request('desde') }}">
        </div>
        <div class="f">
            <label for="hasta">Fecha final</label>
            <input id="hasta" type="date" name="hasta" value="{{ request('hasta') }}">
        </div>
        <div class="actions">
            <button class="btn" type="submit">Filtrar</button>
            <a class="btn out" href="{{ route('ventas.index') }}">Limpiar</a>
        </div>
    </form>
</div>

<div class="card scroll">
    <table>
        <thead>
            <tr>
                <th>Número de venta</th>
                <th>Fecha y hora</th>
                <th>Sucursal</th>
                <th>Cajero</th>
                <th>Total</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ventas as $venta)
                <tr>
                    <td>#{{ $venta->id_venta }}</td>
                    <td>{{ $venta->fecha_venta?->format('d/m/Y H:i') }}</td>
                    <td>{{ $venta->sucursal?->nombre ?: '—' }}</td>
                    <td>{{ $venta->usuario?->nombre_usuario ?: '—' }}</td>
                    <td>Bs {{ number_format((float) $venta->total, 2, ',', '.') }}</td>
                    <td>
                        <a class="btn out small" href="{{ route('ventas.show', $venta) }}">Ver detalle de venta</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No hay ventas registradas para el período seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {{ $ventas->links() }}
</div>
@endsection
