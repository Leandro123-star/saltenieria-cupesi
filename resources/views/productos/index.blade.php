@extends('layouts.app')
@section('title', 'Productos e inventario')
@section('content')
<div class="heading">
    <h2>Productos e inventario</h2>
    <a class="btn" href="{{ route('productos.create') }}">+ Nuevo producto</a>
</div>
<div class="card">
    <form class="actions" method="GET">
        <input name="buscar" value="{{ request('buscar') }}" placeholder="Buscar producto" style="max-width:280px">
        <select name="id_categoria" style="max-width:220px">
            <option value="">Todas las categorías</option>
            @foreach($categorias as $cat)
                <option value="{{ $cat->id_categoria }}" @selected((string) request('id_categoria') === (string) $cat->id_categoria)>{{ $cat->nombre }}</option>
            @endforeach
        </select>
        <button class="btn">Filtrar</button>
        <a class="btn out" href="{{ route('productos.index') }}">Limpiar</a>
    </form>
</div>
<div class="card scroll">
    <table>
        <thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Stock por sucursal</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        @forelse($productos as $producto)
            @php
                $nombreProducto = strtolower($producto->nombre);
                $imagenProducto = str_contains($nombreProducto, 'coca') ? 'cocacola.jpg.jpeg' : (str_contains($nombreProducto, 'pollo') ? 'saltena-pollo.jpeg' : (str_contains($nombreProducto, 'queso') ? 'saltena-queso.jpeg' : (str_contains($nombreProducto, 'mixta') ? 'saltena-mixta.jpeg' : 'saltena-carne.jpeg')));
                $imagenUrl = $producto->foto ? asset('storage/'.$producto->foto) : asset('images/productos/'.$imagenProducto);
            @endphp
            <tr>
                <td><img class="product-img" src="{{ $imagenUrl }}" alt="Fotografía de {{ $producto->nombre }}"> <strong>{{ $producto->nombre }}</strong></td>
                <td>{{ $producto->categoria?->nombre ?? '—' }}</td>
                <td>Bs {{ number_format($producto->precio, 2, ',', '.') }}</td>
                <td>
                    @foreach($sucursales as $sucursal)
                        @php $stock = $producto->sucursales->firstWhere('id_sucursal', $sucursal->id_sucursal)?->pivot; @endphp
                        <div>{{ $sucursal->nombre }}: <strong>{{ $stock?->stock_actual ?? 0 }}</strong></div>
                    @endforeach
                </td>
                <td><span class="pill {{ $producto->activo ? '' : 'off' }}">{{ $producto->activo ? 'Activo' : 'Inactivo' }}</span></td>
                <td><a class="btn out small" href="{{ route('productos.edit', $producto) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No hay productos registrados.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $productos->links() }}
</div>
@php
    $primerProducto = $productos->first();
@endphp
<div class="card">
    <h2>Actualizar existencia por sucursal</h2>
    <form method="POST" action="{{ $primerProducto ? route('productos.stock', $primerProducto) : '#' }}" class="formgrid" id="stock-form">
        @csrf @method('PATCH')
        <div class="f"><label>Producto</label><select name="producto_id" onchange="document.getElementById('stock-form').action='/productos/'+this.value+'/stock'" required><option value="">Seleccionar</option>@foreach($productos as $producto)<option value="{{ $producto->id_producto }}">{{ $producto->nombre }}</option>@endforeach</select></div>
        <div class="f"><label>Sucursal</label><select name="id_sucursal" required>@foreach($sucursales as $sucursal)<option value="{{ $sucursal->id_sucursal }}">{{ $sucursal->nombre }}</option>@endforeach</select></div>
        <div class="f"><label>Existencia actual</label><input name="stock_actual" type="number" min="0" required></div>
        <div class="f"><label>Stock mínimo</label><input name="stock_minimo" type="number" min="0" value="0" required></div>
        <div class="actions"><button class="btn" type="submit">Actualizar stock</button></div>
    </form>
</div>
@endsection
