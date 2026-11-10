@extends('layouts.app')
@section('title', 'Métodos de Pago')
@section('content')
<div class="heading"><h2>Gestionar métodos de pago</h2><a class="btn" href="{{ route('metodos-pago.create') }}">+ Nuevo método de pago</a></div>
<div class="card"><form class="actions" method="GET"><input name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre" style="max-width:360px"><button class="btn">Buscar</button><a class="btn out" href="{{ route('metodos-pago.index') }}">Limpiar</a></form></div>
<div class="card scroll"><table><thead><tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($metodos as $metodo)<tr><td><strong>{{ $metodo->nombre }}</strong></td><td><span class="pill {{ $metodo->activo ? '' : 'off' }}">{{ $metodo->activo ? 'Activo' : 'Inactivo' }}</span></td><td class="actions"><a class="btn out small" href="{{ route('metodos-pago.edit', $metodo) }}">Editar</a><form method="POST" action="{{ route('metodos-pago.destroy', $metodo) }}" onsubmit="return confirm('¿Eliminar lógicamente este método de pago?')">@csrf @method('DELETE')<button class="btn out small">Eliminar</button></form></td></tr>
@empty<tr><td colspan="3" class="muted">No hay métodos de pago registrados.</td></tr>@endforelse</tbody></table>{{ $metodos->links() }}</div>
@if($eliminados->isNotEmpty())<div class="card"><h2>Reactivar método eliminado</h2><form class="actions" method="POST" action="{{ route('metodos-pago.restore') }}">@csrf<select name="id_metodo_pago" required style="max-width:360px"><option value="">Seleccione un método</option>@foreach($eliminados as $metodo)<option value="{{ $metodo->id_metodo_pago }}">{{ $metodo->nombre }}</option>@endforeach</select><button class="btn out">Reactivar</button></form></div>@endif
@endsection
