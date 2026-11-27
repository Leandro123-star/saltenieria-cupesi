@extends('layouts.app')
@section('title', 'Crear permiso')
@section('content')
<div class="heading">
    <h2>Crear permiso</h2>
    <a class="btn out" href="{{ route('permisos.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('permisos.store') }}">
        @csrf
        <div class="formgrid">
            <div class="f">
                <label for="nuevo-nombre">Nombre técnico *</label>
                <input id="nuevo-nombre" name="nombre" placeholder="ej. visualizar panel" maxlength="30"
                       pattern="[a-z][a-z0-9_]*( [a-z0-9_]+)*"
                       title="Solo minúsculas, números, guiones bajos y espacios simples entre palabras"
                       value="{{ old('nombre') }}" required autofocus>
            </div>
            <div class="f">
                <label for="nuevo-descripcion">Descripción</label>
                <input id="nuevo-descripcion" name="descripcion" maxlength="100" value="{{ old('descripcion') }}">
            </div>
        </div>
        <div class="actions">
            <button class="btn" type="submit">Crear permiso</button>
        </div>
    </form>
</div>
@endsection
