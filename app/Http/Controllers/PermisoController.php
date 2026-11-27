<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermisoController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_permisos');
        $permisos = Permiso::withCount('roles')->when($request->filled('buscar'), fn ($q) => $q->where('nombre', 'ilike', '%'.$request->string('buscar')->toString().'%'))->orderBy('nombre')->paginate(20)->withQueryString();

        return $request->expectsJson() ? response()->json($permisos) : view('permisos.index', compact('permisos'));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_permisos');

        return view('permisos.create');
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_permisos');
        $this->normalizarNombre($request);
        $data = $request->validate($this->rules(), $this->messages());
        $data['creado_por'] = auth()->id();
        $permiso = Permiso::create($data);

        return $request->expectsJson() ? response()->json($permiso, 201) : redirect()->route('permisos.index')->with('success', 'Permiso registrado.');
    }

    public function update(Request $request, Permiso $permiso)
    {
        $this->authorizePermission('gestionar_permisos');
        $this->normalizarNombre($request);
        $data = $request->validate($this->rules($permiso), $this->messages());
        $data['modificado_por'] = auth()->id();
        $permiso->update($data);

        return $request->expectsJson() ? response()->json($permiso->fresh()) : redirect()->route('permisos.index')->with('success', 'Permiso actualizado.');
    }

    /**
     * Reglas compartidas por el alta y la edición. La clave interna nunca se
     * modifica; el nombre técnico editado no puede chocar con otra clave.
     */
    private function rules(?Permiso $permiso = null): array
    {
        $uniqueName = Rule::unique('permisos', 'nombre');
        if ($permiso) {
            $uniqueName->ignore($permiso->getKey(), 'id_permiso');
        }

        $uniqueClave = function (string $attribute, mixed $value, \Closure $fail) use ($permiso): void {
            $clave = Permiso::claveDesdeNombre((string) $value);

            $exists = Permiso::query()
                ->where('clave', $clave)
                ->when($permiso, fn ($query) => $query->whereKeyNot($permiso->getKey()))
                ->exists();

            if ($exists) {
                $fail('El nombre técnico ya está en uso por otro permiso.');
            }
        };

        return [
            'nombre' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*(?: [a-z0-9_]+)*$/', $uniqueName, $uniqueClave],
            'descripcion' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function normalizarNombre(Request $request): void
    {
        $nombre = $request->input('nombre');

        if (! is_string($nombre)) {
            return;
        }

        $normalizado = preg_replace('/\s+/u', ' ', trim($nombre)) ?? trim($nombre);

        $request->merge(['nombre' => $normalizado]);
    }

    private function messages(): array
    {
        return [
            'nombre.required' => 'El nombre técnico es obligatorio.',
            'nombre.max' => 'El nombre técnico no puede superar los 30 caracteres.',
            'nombre.regex' => 'El nombre técnico debe iniciar con una letra y solo puede contener minúsculas, números, guiones bajos y espacios simples entre palabras.',
            'nombre.unique' => 'Ya existe un permiso con ese nombre técnico.',
            'descripcion.max' => 'La descripción no puede superar los 100 caracteres.',
        ];
    }
}
