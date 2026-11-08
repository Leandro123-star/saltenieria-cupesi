<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Permiso;
use Illuminate\Http\Request;

class PermisoController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_permisos');
        $permisos = Permiso::withCount('roles')->when($request->filled('buscar'), fn ($q) => $q->where('nombre', 'ilike', '%'.$request->string('buscar')->toString().'%'))->orderBy('nombre')->paginate(20)->withQueryString();
        return $request->expectsJson() ? response()->json($permisos) : view('permisos.index', compact('permisos'));
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_permisos');
        $data = $request->validate(['nombre' => ['required', 'string', 'max:60', 'unique:permisos,nombre'], 'descripcion' => ['nullable', 'string', 'max:150']]);
        $data['creado_por'] = auth()->id();
        $permiso = Permiso::create($data);
        return $request->expectsJson() ? response()->json($permiso, 201) : redirect()->route('permisos.index')->with('success', 'Permiso registrado.');
    }

    public function update(Request $request, Permiso $permiso)
    {
        $this->authorizePermission('gestionar_permisos');
        $data = $request->validate(['nombre' => ['required', 'string', 'max:60', 'unique:permisos,nombre,'.$permiso->getKey().',id_permiso'], 'descripcion' => ['nullable', 'string', 'max:150']]);
        $data['modificado_por'] = auth()->id();
        $permiso->update($data);
        return $request->expectsJson() ? response()->json($permiso->fresh()) : redirect()->route('permisos.index')->with('success', 'Permiso actualizado.');
    }
}
