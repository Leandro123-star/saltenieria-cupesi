@extends('layouts.app')
@section('title', $metodoPago->exists ? 'Editar método de pago' : 'Nuevo método de pago')
@section('content')
<div class="heading"><h2>{{ $metodoPago->exists ? 'Editar método de pago' : 'Registrar método de pago' }}</h2><a class="btn out" href="{{ route('metodos-pago.index') }}">Volver</a></div>
<div class="card"><form method="POST" action="{{ $metodoPago->exists ? route('metodos-pago.update', $metodoPago) : route('metodos-pago.store') }}">@csrf @if($metodoPago->exists) @method('PUT') @endif<div class="formgrid"><div class="f"><label>Nombre *</label><input name="nombre" maxlength="30" value="{{ old('nombre', $metodoPago->nombre) }}" required></div><div class="f"><label>Estado</label><select name="activo"><option value="1" @selected(old('activo', $metodoPago->activo ?? true))>Activo</option><option value="0" @selected(!old('activo', $metodoPago->activo ?? true))>Inactivo</option></select></div></div><button class="btn">Guardar método de pago</button></form></div>
@endsection
