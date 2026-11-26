@extends('layouts.app')
@section('title', 'Métodos de Pago')
@section('content')
<div class="heading"><h2>Gestionar métodos de pago</h2><a class="btn" href="{{ route('metodos-pago.create') }}">+ Nuevo método de pago</a></div>
<div class="card scroll"><table><thead><tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($metodos as $metodo)<tr><td><strong>{{ $metodo->nombre }}</strong></td><td><span class="pill {{ $metodo->activo ? '' : 'off' }}">{{ $metodo->activo ? 'Activo' : 'Inactivo' }}</span></td><td class="actions"><a class="btn out small" href="{{ route('metodos-pago.edit', $metodo) }}">Editar</a>@if($metodo->activo)<form method="POST" action="{{ route('metodos-pago.destroy', $metodo) }}" onsubmit="return confirm('¿Desactivar este método de pago?')">@csrf @method('DELETE')<button class="btn out small">Desactivar</button></form>@else<form method="POST" action="{{ route('metodos-pago.restore', $metodo) }}" onsubmit="return confirm('¿Activar este método de pago?')">@csrf @method('PATCH')<button class="btn out small">Activar</button></form>@endif</td></tr>
@empty<tr><td colspan="3" class="muted">No hay métodos de pago registrados.</td></tr>@endforelse</tbody></table>{{ $metodos->links() }}</div>
@endsection
