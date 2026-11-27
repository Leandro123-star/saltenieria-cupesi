<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Permiso;
use App\Models\Rol;
use App\Support\Formatos;
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
            'nombre' => ['required', 'string', 'max:30', 'regex:'.Formatos::NOMBRE_ROL, 'unique:roles,nombre'],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => ['integer', 'distinct', 'exists:permisos,id_permiso'],
        ], [
            'nombre.regex' => 'El nombre del rol debe empezar con una letra y solo puede contener letras, números, espacios y los signos / . -',
            'nombre.unique' => 'Ya existe un rol con ese nombre.',
            'permisos.*.exists' => 'Selecciona permisos registrados.',
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
            'nombre' => ['required', 'string', 'max:30', 'regex:'.Formatos::NOMBRE_ROL, 'unique:roles,nombre,'.$rol->getKey().',id_rol'],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => ['integer', 'distinct', 'exists:permisos,id_permiso'],
        ], [
            'nombre.regex' => 'El nombre del rol debe empezar con una letra y solo puede contener letras, números, espacios y los signos / . -',
            'nombre.unique' => 'Ya existe un rol con ese nombre.',
            'permisos.*.exists' => 'Selecciona permisos registrados.',
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
        $mensaje = match (true) {
            $rol->usuarios()->exists() => 'No se puede desactivar un rol con usuarios asignados.',
            in_array($rol->nombre, ['Administrador', 'Cajero/Vendedor'], true) => 'El rol es esencial y no puede desactivarse.',
            default => null,
        };
        if ($mensaje !== null) {
            abort_if(request()->expectsJson(), 422, $mensaje);

            return redirect()->route('roles.index')->with('error', $mensaje);
        }
        $rol->update(['activo' => false, 'modificado_por' => auth()->id()]);
        return request()->expectsJson() ? response()->json(['message' => 'Rol desactivado.']) : redirect()->route('roles.index')->with('success', 'Rol desactivado.');
    }

    public function syncPermisos(Request $request, Rol $rol)
    {
        $this->authorizePermission('gestionar_permisos');
        $data = $request->validate(['permisos' => ['required', 'array'], 'permisos.*' => ['integer', 'exists:permisos,id_permiso']]);
        $rol->permisos()->sync($data['permisos']);
        return $request->expectsJson() ? response()->json($rol->fresh('permisos')) : redirect()->route('roles.edit', $rol)->with('success', 'Permisos actualizados.');
    }
}
