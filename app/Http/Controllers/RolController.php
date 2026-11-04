<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    use AuthorizesSistema;

    public function index()
    {
        $this->authorizePermission('gestionar_roles');
        return response()->json(Rol::with('permisos')->paginate(15));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_roles');
        return response()->json(['permisos' => Permiso::all(), 'message' => 'Formulario pendiente.']);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_roles');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:30', 'unique:roles,nombre'],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => ['integer', 'exists:permisos,id_permiso'],
        ]);
        $permisos = $data['permisos'] ?? [];
        unset($data['permisos']);
        $data['creado_por'] = auth()->id();
        $rol = Rol::create($data);
        $rol->permisos()->sync($permisos);
        return response()->json($rol->load('permisos'), 201);
    }

    public function show(Rol $rol)
    {
        $this->authorizePermission('gestionar_roles');
        return response()->json($rol->load(['permisos', 'usuarios']));
    }

    public function edit(Rol $rol)
    {
        return $this->show($rol);
    }

    public function update(Request $request, Rol $rol)
    {
        $this->authorizePermission('gestionar_roles');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:30', 'unique:roles,nombre,'.$rol->getKey().',id_rol'],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => ['integer', 'exists:permisos,id_permiso'],
        ]);
        $permisos = $data['permisos'] ?? null;
        unset($data['permisos']);
        $data['modificado_por'] = auth()->id();
        $rol->update($data);
        if ($permisos !== null) $rol->permisos()->sync($permisos);
        return response()->json($rol->fresh('permisos'));
    }

    public function destroy(Rol $rol)
    {
        $this->authorizePermission('gestionar_roles');
        abort_if(in_array($rol->nombre, ['Administrador', 'Cajero/Vendedor'], true), 422, 'El rol es esencial y no puede eliminarse.');
        $rol->eliminado_por = auth()->id();
        $rol->save();
        $rol->delete();
        return response()->json(['message' => 'Rol eliminado lógicamente.']);
    }

    public function syncPermisos(Request $request, Rol $rol)
    {
        $this->authorizePermission('gestionar_permisos');
        $data = $request->validate(['permisos' => ['required', 'array'], 'permisos.*' => ['integer', 'exists:permisos,id_permiso']]);
        $rol->permisos()->sync($data['permisos']);
        return response()->json($rol->fresh('permisos'));
    }
}
