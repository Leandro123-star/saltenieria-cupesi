<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Persona;
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
        $usuarios = Usuario::with(['persona', 'rol', 'sucursales'])->paginate(15);
        return request()->expectsJson() ? response()->json($usuarios) : view('usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_usuarios');
        $viewData = [
            'roles' => Rol::where('activo', true)->orderBy('nombre')->get(),
            'sucursales' => \App\Models\Sucursal::where('activo', true)->orderBy('nombre')->get(),
            'personas' => $this->eligiblePersonas(),
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
            'id_persona' => [
                'required',
                'integer',
                'unique:usuarios,id_persona',
                Rule::exists('personas', 'id_persona')->where(fn ($query) => $query->where('activo', true)),
            ],
            'id_rol' => [
                'required',
                'integer',
                Rule::exists('roles', 'id_rol')->where(fn ($query) => $query->where('activo', true)),
            ],
            'activo' => ['sometimes', 'boolean'],
            'sucursales' => ['sometimes', 'array'],
            'sucursales.*.id_sucursal' => ['integer', 'exists:sucursales,id_sucursal'],
            'sucursales.*.fecha_asignacion' => ['nullable', 'date'],
            'sucursales.*.fecha_fin' => ['nullable', 'date'],
        ]);
        $this->validateSucursalAssignment((int) $data['id_rol'], $data['sucursales'] ?? []);

        $usuario = DB::transaction(function () use ($data): Usuario {
            $sucursales = $data['sucursales'] ?? [];
            $password = $data['password'];
            unset($data['sucursales'], $data['password']);
            // El formulario no expone el estado al crear usuarios; los nuevos
            // usuarios deben quedar activos salvo que una integración lo envíe
            // explícitamente como false.
            $data['activo'] = $data['activo'] ?? true;
            $data['password_hash'] = Hash::make($password);
            $data['creado_por'] = auth()->id();
            $usuario = Usuario::create($data);
            $usuario->sucursales()->sync(collect($sucursales)->mapWithKeys(fn ($item) => [
                $item['id_sucursal'] => array_filter([
                    'fecha_asignacion' => $item['fecha_asignacion'] ?? null,
                    'fecha_fin' => $item['fecha_fin'] ?? null,
                ], fn ($value) => $value !== null),
            ])->all());

            return $usuario;
        });

        return $request->expectsJson() ? response()->json($usuario->load(['rol', 'persona', 'sucursales']), 201) : redirect()->route('usuarios.index')->with('success', 'Usuario registrado.');
    }

    public function show(Usuario $usuario)
    {
        $this->authorizePermission('gestionar_usuarios');
        $usuario->load(['persona', 'rol', 'sucursales']);
        return request()->expectsJson() ? response()->json($usuario) : view('usuarios.form', [
            'usuario' => $usuario,
            'roles' => Rol::where('activo', true)->orderBy('nombre')->get(),
            'sucursales' => \App\Models\Sucursal::where('activo', true)->orderBy('nombre')->get(),
            'personas' => $this->eligiblePersonas($usuario),
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
            'id_persona' => [
                'required',
                'integer',
                Rule::unique('usuarios', 'id_persona')->ignore($usuario->getKey(), 'id_usuario'),
                Rule::exists('personas', 'id_persona')->where(fn ($query) => $query->where('activo', true)),
            ],
            'id_rol' => [
                'required',
                'integer',
                Rule::exists('roles', 'id_rol')->where(fn ($query) => $query->where('activo', true)),
            ],
            'activo' => ['sometimes', 'boolean'],
            'sucursales' => ['sometimes', 'array'],
            'sucursales.*.id_sucursal' => ['integer', 'exists:sucursales,id_sucursal'],
        ]);
        $sucursales = $data['sucursales'] ?? null;
        $assignmentsForValidation = $sucursales ?? $usuario->sucursales()->get()->map(fn ($sucursal) => ['id_sucursal' => $sucursal->getKey()])->all();
        $this->validateSucursalAssignment((int) $data['id_rol'], $assignmentsForValidation);
        unset($data['sucursales']);
        DB::transaction(function () use ($data, $sucursales, $usuario): void {
            $data['modificado_por'] = auth()->id();
            $usuario->update($data);
            if ($sucursales !== null) {
                $usuario->sucursales()->sync(collect($sucursales)->pluck('id_sucursal')->all());
            }
        });

        return $request->expectsJson() ? response()->json($usuario->fresh(['rol', 'persona', 'sucursales'])) : redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    private function validateSucursalAssignment(int $roleId, array $sucursales): void
    {
        $isCajero = Rol::whereKey($roleId)->whereIn('nombre', ['Cajero', 'Cajero/Vendedor'])->exists();
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

    private function eligiblePersonas(?Usuario $usuario = null)
    {
        $linkedPersonas = Usuario::query()
            ->whereNotNull('id_persona')
            ->when($usuario, fn ($query) => $query->where('id_usuario', '!=', $usuario->getKey()))
            ->select('id_persona');

        return Persona::query()
            ->where('activo', true)
            ->whereNotIn('id_persona', $linkedPersonas)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();
    }

    public function destroy(Usuario $usuario)
    {
        $this->authorizePermission('gestionar_usuarios');
        $usuario->update(['activo' => false, 'modificado_por' => auth()->id()]);
        return response()->json(['message' => 'Usuario desactivado.']);
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
