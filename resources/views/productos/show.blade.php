@extends('layouts.app')
@section('title', 'Detalle de producto')
@section('content')
@php
    $nombreProducto = strtolower($producto->nombre);
    $imagenProducto = str_contains($nombreProducto, 'coca') ? 'cocacola.jpg.jpeg' : (str_contains($nombreProducto, 'pollo') ? 'saltena-pollo.jpeg' : (str_contains($nombreProducto, 'queso') ? 'saltena-queso.jpeg' : (str_contains($nombreProducto, 'mixta') ? 'saltena-mixta.jpeg' : 'saltena-carne.jpeg')));
    $imagenUrl = $producto->foto ? asset('storage/'.$producto->foto) : asset('images/productos/'.$imagenProducto);
@endphp
<div class="heading">
    <h2>{{ $producto->nombre }}</h2>
    <div class="actions">
        <a class="btn out" href="{{ route('productos.index') }}">Volver a productos</a>
        <a class="btn" href="{{ route('productos.edit', $producto) }}">Editar</a>
    </div>
</div>
<div class="split">
    <div class="card">
        <img src="{{ $imagenUrl }}" alt="Fotografía de {{ $producto->nombre }}" style="width:100%;max-width:320px;height:220px;object-fit:cover;border-radius:12px;border:1px solid var(--line);margin-bottom:16px">
        <div class="formgrid">
            <div class="f">
                <label>Categoría</label>
                <div><strong>{{ $producto->categoria?->nombre ?? '—' }}</strong></div>
            </div>
            <div class="f">
                <label>Precio de venta</label>
                <div><strong>Bs {{ number_format($producto->precio, 2, ',', '.') }}</strong></div>
            </div>
            <div class="f">
                <label>Estado</label>
                <div><span class="pill {{ $producto->activo ? '' : 'off' }}">{{ $producto->activo ? 'Activo' : 'Inactivo' }}</span></div>
            </div>
        </div>
        <div class="f">
            <label>Descripción</label>
            @if($producto->descripcion)
                <p style="margin:0">{!! nl2br(e($producto->descripcion)) !!}</p>
            @else
                <p class="muted" style="margin:0">Sin descripción registrada.</p>
            @endif
        </div>
    </div>
    <div class="card">
        <h2>Stock por sucursal</h2>
        <div class="scroll">
            <table>
                <thead><tr><th>Sucursal</th><th>Stock actual</th><th>Stock mínimo</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse($sucursales as $sucursal)
                    @php $stock = $producto->sucursales->firstWhere('id_sucursal', $sucursal->id_sucursal)?->pivot; @endphp
                    <tr>
                        <td>{{ $sucursal->nombre }}</td>
                        <td><strong>{{ $stock?->stock_actual ?? 0 }}</strong></td>
                        <td>{{ $stock?->stock_minimo ?? '—' }}</td>
                        <td>
                            @if($stock)
                                <span class="pill {{ $stock->stock_actual <= $stock->stock_minimo ? 'off' : '' }}">{{ $stock->stock_actual <= $stock->stock_minimo ? 'Bajo' : 'Disponible' }}</span>
                            @else
                                <span class="muted">Sin registro</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No hay sucursales disponibles.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
