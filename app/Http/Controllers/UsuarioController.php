<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    use AuthorizesSistema;

    public function index()
    {
        $this->authorizePermission('gestionar_usuarios');
        $usuarios = Usuario::with(['personal', 'roles', 'sucursales'])->paginate(15);
        return request()->expectsJson() ? response()->json($usuarios) : view('usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_usuarios');
        $viewData = [
            'roles' => Rol::orderBy('nombre')->get(),
            'sucursales' => \App\Models\Sucursal::where('activo', true)->orderBy('nombre')->get(),
            'personal' => $this->eligiblePersonal(),
            'usuario' => new Usuario,
        ];
        return request()->expectsJson() ? response()->json($viewData) : view('usuarios.form', $viewData);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_usuarios');
        $data = $request->validate([
            'nombre_usuario' => ['required', 'string', 'max:30', 'unique:usuarios,nombre_usuario'],
            'password' => ['required', 'string', 'min:8', 'max:50'],
            'id_personal' => [
                'required',
                'integer',
                'unique:usuarios,id_personal',
                Rule::exists('personal', 'id_personal')->where(fn ($query) => $query->where('activo', true)),
            ],
            'activo' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', 'exists:roles,id_rol'],
            'sucursales' => ['sometimes', 'array'],
            'sucursales.*.id_sucursal' => ['integer', 'exists:sucursales,id_sucursal'],
            'sucursales.*.fecha_asignacion' => ['nullable', 'date'],
            'sucursales.*.fecha_fin' => ['nullable', 'date'],
        ]);
        $this->validateSucursalAssignment($data['roles'] ?? [], $data['sucursales'] ?? []);

        $usuario = DB::transaction(function () use ($data): Usuario {
            $roles = $data['roles'] ?? [];
            $sucursales = $data['sucursales'] ?? [];
            $password = $data['password'];
            unset($data['roles'], $data['sucursales'], $data['password']);
            // El formulario no expone el estado al crear usuarios; los nuevos
            // usuarios deben quedar activos salvo que una integración lo envíe
            // explícitamente como false.
            $data['activo'] = $data['activo'] ?? true;
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

        return $request->expectsJson() ? response()->json($usuario->load(['roles', 'sucursales']), 201) : redirect()->route('usuarios.index')->with('success', 'Usuario registrado.');
    }

    public function show(Usuario $usuario)
    {
        $this->authorizePermission('gestionar_usuarios');
        $usuario->load(['personal', 'roles', 'sucursales']);
        return request()->expectsJson() ? response()->json($usuario) : view('usuarios.form', [
            'usuario' => $usuario,
            'roles' => Rol::orderBy('nombre')->get(),
            'sucursales' => \App\Models\Sucursal::where('activo', true)->orderBy('nombre')->get(),
            'personal' => $this->eligiblePersonal($usuario),
        ]);
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
            'id_personal' => [
                'required',
                'integer',
                Rule::unique('usuarios', 'id_personal')->ignore($usuario->getKey(), 'id_usuario'),
                Rule::exists('personal', 'id_personal')->where(fn ($query) => $query->where('activo', true)),
            ],
            'activo' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', 'exists:roles,id_rol'],
            'sucursales' => ['sometimes', 'array'],
            'sucursales.*.id_sucursal' => ['integer', 'exists:sucursales,id_sucursal'],
        ]);
        $roles = $data['roles'] ?? null;
        $sucursales = $data['sucursales'] ?? null;
        $rolesForValidation = $data['roles'] ?? $usuario->roles()->pluck('roles.id_rol')->all();
        $assignmentsForValidation = $sucursales ?? $usuario->sucursales()->get()->map(fn ($sucursal) => ['id_sucursal' => $sucursal->getKey()])->all();
        $this->validateSucursalAssignment($rolesForValidation, $assignmentsForValidation);
        unset($data['roles'], $data['sucursales']);
        DB::transaction(function () use ($data, $roles, $sucursales, $usuario): void {
            $data['modificado_por'] = auth()->id();
            $usuario->update($data);
            if ($roles !== null) $usuario->roles()->sync($roles);
            if ($sucursales !== null) {
                $usuario->sucursales()->sync(collect($sucursales)->pluck('id_sucursal')->all());
            }
        });

        return $request->expectsJson() ? response()->json($usuario->fresh(['roles', 'sucursales'])) : redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    private function validateSucursalAssignment(array $roleIds, array $sucursales): void
    {
        $isCajero = Rol::whereIn('id_rol', $roleIds)->whereIn('nombre', ['Cajero', 'Cajero/Vendedor'])->exists();
        if ($isCajero && count($sucursales) !== 1) {
            throw ValidationException::withMessages(['sucursales' => 'Un Cajero debe tener exactamente una sucursal asignada.']);
        }
        if ($isCajero && ! \App\Models\Sucursal::whereKey($sucursales[0]['id_sucursal'])->where('activo', true)->exists()) {
            throw ValidationException::withMessages(['sucursales' => 'La sucursal asignada al Cajero debe estar activa.']);
        }
        if (count(collect($sucursales)->pluck('id_sucursal')->unique()) !== count($sucursales)) {
            throw ValidationException::withMessages(['sucursales' => 'No se puede asignar la misma sucursal más de una vez.']);
        }
    }

    private function eligiblePersonal(?Usuario $usuario = null)
    {
        $linkedPersonal = Usuario::query()
            ->whereNotNull('id_personal')
            ->when($usuario, fn ($query) => $query->where('id_usuario', '!=', $usuario->getKey()))
            ->select('id_personal');

        return Personal::query()
            ->where('activo', true)
            ->whereNotIn('id_personal', $linkedPersonal)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();
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
