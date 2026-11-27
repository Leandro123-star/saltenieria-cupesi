@extends('layouts.app')
@section('title', 'Permisos')
@section('content')
<div class="heading">
    <h2>Catálogo de permisos</h2>
    <a class="btn" href="{{ route('permisos.create') }}">+ Nuevo permiso</a>
</div>

<div class="card scroll">
    <table>
        <thead>
            <tr>
                <th>Permiso</th>
                <th>Descripción</th>
                <th>Roles asignados</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            @forelse($permisos as $permiso)
                <tr>
                    <td><strong>{{ $permiso->nombre }}</strong></td>
                    <td>{{ $permiso->descripcion ?: '—' }}</td>
                    <td>{{ $permiso->roles_count }}</td>
                    <td>
                        <button type="button" class="btn small permiso-edit"
                                data-id="{{ $permiso->getKey() }}"
                                data-nombre="{{ $permiso->nombre }}"
                                data-descripcion="{{ $permiso->descripcion }}"
                                data-action="{{ route('permisos.update', $permiso) }}">Editar</button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">No hay permisos.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    {{ $permisos->links() }}
</div>

<dialog class="modal" id="permisoModal" aria-labelledby="permiso-modal-title">
    <form method="POST" id="permisoEditForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="permiso_id" id="permiso-id" value="{{ old('permiso_id') }}">
        <h2 id="permiso-modal-title">Editar permiso</h2>
        <div class="f">
            <label for="permiso-nombre-actual">Nombre técnico actual</label>
            <input id="permiso-nombre-actual" type="text" readonly>
        </div>
        <div class="f">
            <label for="permiso-nombre">Nuevo nombre técnico *</label>
            <input id="permiso-nombre" name="nombre" maxlength="30"
                   pattern="[a-z][a-z0-9_]*( [a-z0-9_]+)*"
                   title="Solo minúsculas, números, guiones bajos y espacios simples entre palabras" required>
        </div>
        <div class="f">
            <label for="permiso-descripcion">Descripción</label>
            <input id="permiso-descripcion" name="descripcion" maxlength="100">
        </div>
        <div class="actions modal-actions">
            <button class="btn" type="submit">Guardar cambios</button>
            <button class="btn out" type="button" data-close-modal>Cancelar</button>
        </div>
    </form>
</dialog>

<script>
(() => {
    const modal = document.getElementById('permisoModal');
    const form = document.getElementById('permisoEditForm');
    if (!modal || !form) return;

    const idInput = document.getElementById('permiso-id');
    const nombreActual = document.getElementById('permiso-nombre-actual');
    const nombre = document.getElementById('permiso-nombre');
    const descripcion = document.getElementById('permiso-descripcion');
    const editable = @json(old('permiso_id') !== null);
    const oldNombre = @json(old('nombre'));
    const oldDescripcion = @json(old('descripcion'));

    const openFor = (button) => {
        form.action = button.dataset.action;
        idInput.value = button.dataset.id;
        nombreActual.value = button.dataset.nombre;
        nombre.value = button.dataset.nombre;
        descripcion.value = button.dataset.descripcion || '';
        modal.showModal();
        nombre.focus();
        nombre.select();
    };

    document.querySelectorAll('.permiso-edit').forEach((button) => {
        button.addEventListener('click', () => openFor(button));
    });

    modal.querySelector('[data-close-modal]').addEventListener('click', () => modal.close());
    modal.addEventListener('click', (event) => {
        if (event.target === modal) modal.close();
    });

    if (editable && idInput.value) {
        const button = document.querySelector('.permiso-edit[data-id="' + idInput.value + '"]');
        if (button) {
            openFor(button);
            if (oldNombre !== null) nombre.value = oldNombre;
            if (oldDescripcion !== null) descripcion.value = oldDescripcion;
        }
    }
})();
</script>
@endsection
