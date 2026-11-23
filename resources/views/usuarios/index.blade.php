@extends('layouts.app')
@section('title', 'Gestionar usuarios')
@section('content')
<div class="heading"><h2>Gestionar usuarios</h2><a class="btn" href="{{ route('usuarios.create') }}">+ Nuevo usuario</a></div>
<div class="card scroll"><table><thead><tr><th>Personal asociado</th><th>Usuario</th><th>Roles</th><th>Sucursales autorizadas</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
@forelse($usuarios as $usuario)<tr><td>{{ $usuario->personal ? $usuario->personal->nombres.' '.$usuario->personal->apellidos : 'Sin asociación' }}</td><td>{{ $usuario->nombre_usuario }}</td><td>{{ $usuario->roles->pluck('nombre')->join(', ') ?: '—' }}</td><td>{{ $usuario->sucursales->pluck('nombre')->join(', ') ?: 'Sin asignación' }}</td><td>{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</td><td><a class="btn out small" href="{{ route('usuarios.show', $usuario) }}">Editar</a></td></tr>
@empty<tr><td colspan="6" class="muted">No hay usuarios registrados.</td></tr>@endforelse</tbody></table>{{ $usuarios->links() }}</div>
@endsection
