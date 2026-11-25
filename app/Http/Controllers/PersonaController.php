<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Persona;
use Illuminate\Http\Request;

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
        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:30'],
            'apellidos' => ['required', 'string', 'max:30'],
            'ci' => ['nullable', 'string', 'max:20', 'unique:personas,ci'],
            'telefono' => ['nullable', 'string', 'max:15'],
            'activo' => ['sometimes', 'boolean'],
        ]);
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
        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:30'],
            'apellidos' => ['required', 'string', 'max:30'],
            'ci' => ['nullable', 'string', 'max:20', 'unique:personas,ci,'.$persona->getKey().',id_persona'],
            'telefono' => ['nullable', 'string', 'max:15'],
            'activo' => ['sometimes', 'boolean'],
        ]);
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
}
