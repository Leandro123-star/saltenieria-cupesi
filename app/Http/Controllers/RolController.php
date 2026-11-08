<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_roles');
        $roles = Rol::with('permisos')->withCount('usuarios')->orderBy('nombre')->paginate(15);
        return $request->expectsJson() ? response()->json($roles) : view('roles.index', compact('roles'));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_roles');
        return view('roles.form', ['rol' => new Rol, 'permisos' => Permiso::orderBy('nombre')->get()]);
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
        return $request->expectsJson() ? response()->json($rol->load('permisos'), 201) : redirect()->route('roles.index')->with('success', 'Rol registrado.');
    }

    public function show(Rol $rol)
    {
        $this->authorizePermission('gestionar_roles');
        return request()->expectsJson() ? response()->json($rol->load(['permisos', 'usuarios'])) : view('roles.form', ['rol' => $rol, 'permisos' => Permiso::orderBy('nombre')->get()]);
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
        return $request->expectsJson() ? response()->json($rol->fresh('permisos')) : redirect()->route('roles.index')->with('success', 'Rol actualizado.');
    }

    public function destroy(Rol $rol)
    {
        $this->authorizePermission('gestionar_roles');
        abort_if(in_array($rol->nombre, ['Administrador', 'Cajero/Vendedor'], true), 422, 'El rol es esencial y no puede eliminarse.');
        $rol->eliminado_por = auth()->id();
        $rol->save();
        $rol->delete();
        return request()->expectsJson() ? response()->json(['message' => 'Rol eliminado lógicamente.']) : redirect()->route('roles.index')->with('success', 'Rol desactivado.');
    }

    public function syncPermisos(Request $request, Rol $rol)
    {
        $this->authorizePermission('gestionar_permisos');
        $data = $request->validate(['permisos' => ['required', 'array'], 'permisos.*' => ['integer', 'exists:permisos,id_permiso']]);
        $rol->permisos()->sync($data['permisos']);
        return $request->expectsJson() ? response()->json($rol->fresh('permisos')) : redirect()->route('roles.edit', $rol)->with('success', 'Permisos actualizados.');
    }
}
