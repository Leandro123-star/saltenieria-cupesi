<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Persona;
use App\Support\Formatos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonaController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_personas');
        $search = $request->string('buscar')->toString();

        $personas = Persona::query()
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('nombres', 'ilike', "%{$search}%")
                ->orWhere('apellidos', 'ilike', "%{$search}%")))
            ->orderBy('apellidos')->paginate(15);

        return $request->expectsJson() ? response()->json($personas) : view('personas.index', compact('personas'));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_personas');
        return view('personas.form', ['persona' => new Persona]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_personas');
        $data = $request->validate($this->rules($request), $this->messages());
        $data['creado_por'] = auth()->id();

        $persona = Persona::create($data);
        return $request->expectsJson() ? response()->json($persona, 201) : redirect()->route('personas.index')->with('success', 'Persona registrada.');
    }

    public function show(Persona $persona)
    {
        $this->authorizePermission('gestionar_personas');
        return request()->expectsJson() ? response()->json($persona->load('usuario')) : view('personas.form', ['persona' => $persona]);
    }

    public function edit(Persona $persona)
    {
        return $this->show($persona);
    }

    public function update(Request $request, Persona $persona)
    {
        $this->authorizePermission('gestionar_personas');
        $data = $request->validate($this->rules($request, $persona), $this->messages());
        $data['modificado_por'] = auth()->id();
        $persona->update($data);

        return $request->expectsJson() ? response()->json($persona->fresh()) : redirect()->route('personas.index')->with('success', 'Persona actualizada.');
    }

    public function destroy(Persona $persona)
    {
        $this->authorizePermission('gestionar_personas');
        $persona->update(['activo' => false, 'modificado_por' => auth()->id()]);

        return request()->expectsJson() ? response()->json(['message' => 'Persona desactivada.']) : redirect()->route('personas.index')->with('success', 'Persona desactivada.');
    }

    private function rules(Request $request, ?Persona $persona = null): array
    {
        Formatos::normalizar($request, ['nombres', 'apellidos', 'ci', 'telefono']);

        return [
            'nombres' => ['required', 'string', 'max:30', 'regex:'.Formatos::NOMBRE_PERSONA],
            'apellidos' => ['required', 'string', 'max:30', 'regex:'.Formatos::NOMBRE_PERSONA],
            'ci' => ['nullable', 'string', 'max:20', 'regex:'.Formatos::CI,
                Rule::unique('personas', 'ci')->ignore($persona?->getKey(), 'id_persona')],
            'telefono' => ['nullable', 'string', 'max:15', 'regex:'.Formatos::TELEFONO],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'nombres.regex' => 'El nombre no debe contener números ni símbolos.',
            'apellidos.regex' => 'El apellido no debe contener números ni símbolos.',
            'ci.regex' => 'El CI debe tener solo números y, si corresponde, un complemento (ejemplo: 1234567-1B).',
            'ci.unique' => 'Ya existe una persona registrada con ese CI.',
            'telefono.regex' => 'El teléfono tiene un formato incorrecto: usa de 7 a 14 dígitos, con + opcional al inicio.',
        ];
    }
}
