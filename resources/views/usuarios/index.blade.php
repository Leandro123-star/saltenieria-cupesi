@extends('layouts.app')
@section('title', 'Gestionar usuarios')
@section('content')
<div class="heading"><h2>Gestionar usuarios</h2><a class="btn" href="{{ route('usuarios.create') }}">+ Nuevo usuario</a></div>
<div class="card scroll"><table><thead><tr><th>Persona asociada</th><th>Usuario</th><th>Rol</th><th>Sucursales autorizadas</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
@forelse($usuarios as $usuario)<tr><td>{{ $usuario->persona ? $usuario->persona->nombres.' '.$usuario->persona->apellidos : 'Sin asociación' }}</td><td>{{ $usuario->nombre_usuario }}</td><td>{{ $usuario->rol?->nombre ?? '—' }}</td><td>@if($usuario->tieneAccesoGlobal())<span class="pill" style="background:#fff7f1;color:#a63c03">Todas las sucursales</span>@else{{ $usuario->sucursales->pluck('nombre')->join(', ') ?: 'Sin asignación' }}@endif</td><td>{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</td><td><a class="btn out small" href="{{ route('usuarios.edit', $usuario) }}">Editar</a></td></tr>
@empty<tr><td colspan="6" class="muted">No hay usuarios registrados.</td></tr>@endforelse</tbody></table>{{ $usuarios->links() }}</div>
@endsection
