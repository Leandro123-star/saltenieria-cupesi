<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    use AuthorizesSistema;

    public function index()
    {
        $this->authorizePermission('gestionar_usuarios');
        return response()->json(Usuario::with(['personal', 'roles', 'sucursales'])->paginate(15));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_usuarios');
        return response()->json(['roles' => Rol::query()->get(), 'message' => 'Formulario pendiente.']);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_usuarios');
        $data = $request->validate([
            'nombre_usuario' => ['required', 'string', 'max:30', 'unique:usuarios,nombre_usuario'],
            'password' => ['required', 'string', 'min:8', 'max:50'],
            'id_personal' => ['nullable', 'integer', 'unique:usuarios,id_personal', 'exists:personal,id_personal'],
            'activo' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', 'exists:roles,id_rol'],
            'sucursales' => ['sometimes', 'array'],
            'sucursales.*.id_sucursal' => ['integer', 'exists:sucursales,id_sucursal'],
            'sucursales.*.fecha_asignacion' => ['nullable', 'date'],
            'sucursales.*.fecha_fin' => ['nullable', 'date'],
        ]);

        $usuario = DB::transaction(function () use ($data): Usuario {
            $roles = $data['roles'] ?? [];
            $sucursales = $data['sucursales'] ?? [];
            $password = $data['password'];
            unset($data['roles'], $data['sucursales'], $data['password']);
            $data['password_hash'] = Hash::make($password);
            $data['creado_por'] = auth()->id();
            $usuario = Usuario::create($data);
            $usuario->roles()->sync($roles);
            $usuario->sucursales()->sync(collect($sucursales)->mapWithKeys(fn ($item) => [
                $item['id_sucursal'] => array_filter([
                    'fecha_asignacion' => $item['fecha_asignacion'] ?? null,
                    'fecha_fin' => $item['fecha_fin'] ?? null,
                ], fn ($value) => $value !== null),
            ])->all());

            return $usuario;
        });

        return response()->json($usuario->load(['roles', 'sucursales']), 201);
    }

    public function show(Usuario $usuario)
    {
        $this->authorizePermission('gestionar_usuarios');
        return response()->json($usuario->load(['personal', 'roles', 'sucursales']));
    }

    public function edit(Usuario $usuario)
    {
        return $this->show($usuario);
    }

    public function update(Request $request, Usuario $usuario)
    {
        $this->authorizePermission('gestionar_usuarios');
        $data = $request->validate([
            'nombre_usuario' => ['required', 'string', 'max:30', 'unique:usuarios,nombre_usuario,'.$usuario->getKey().',id_usuario'],
            'id_personal' => ['nullable', 'integer', 'exists:personal,id_personal', 'unique:usuarios,id_personal,'.$usuario->getKey().',id_usuario'],
            'activo' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', 'exists:roles,id_rol'],
            'sucursales' => ['sometimes', 'array'],
            'sucursales.*.id_sucursal' => ['integer', 'exists:sucursales,id_sucursal'],
        ]);
        $roles = $data['roles'] ?? null;
        $sucursales = $data['sucursales'] ?? null;
        unset($data['roles'], $data['sucursales']);
        $data['modificado_por'] = auth()->id();
        $usuario->update($data);
        if ($roles !== null) $usuario->roles()->sync($roles);
        if ($sucursales !== null) $usuario->sucursales()->sync(collect($sucursales)->pluck('id_sucursal')->all());

        return response()->json($usuario->fresh(['roles', 'sucursales']));
    }

    public function destroy(Usuario $usuario)
    {
        $this->authorizePermission('gestionar_usuarios');
        $usuario->eliminado_por = auth()->id();
        $usuario->save();
        $usuario->delete();
        return response()->json(['message' => 'Usuario eliminado lógicamente.']);
    }

    public function resetPassword(Request $request, Usuario $usuario)
    {
        $this->authorizePermission('gestionar_usuarios');
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:50']]);
        $usuario->password_hash = Hash::make($data['password']);
        $usuario->modificado_por = auth()->id();
        $usuario->save();
        return response()->json(['message' => 'Contraseña restablecida.']);
    }
}
