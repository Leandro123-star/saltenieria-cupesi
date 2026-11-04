<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Personal;
use Illuminate\Http\Request;

class PersonalController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_personal');
        $search = $request->string('buscar')->toString();

        $personal = Personal::query()
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('nombres', 'ilike', "%{$search}%")
                ->orWhere('apellidos', 'ilike', "%{$search}%")))
            ->orderBy('apellidos')->paginate(15);

        return response()->json($personal);
    }

    public function create()
    {
        $this->authorizePermission('gestionar_personal');
        return response()->json(['message' => 'Formulario de personal pendiente.']);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_personal');
        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:30'],
            'apellidos' => ['required', 'string', 'max:30'],
            'ci' => ['nullable', 'string', 'max:20', 'unique:personal,ci'],
            'telefono' => ['nullable', 'string', 'max:15'],
            'activo' => ['sometimes', 'boolean'],
        ]);
        $data['creado_por'] = auth()->id();

        return response()->json(Personal::create($data), 201);
    }

    public function show(Personal $personal)
    {
        $this->authorizePermission('gestionar_personal');
        return response()->json($personal->load('usuario'));
    }

    public function edit(Personal $personal)
    {
        return $this->show($personal);
    }

    public function update(Request $request, Personal $personal)
    {
        $this->authorizePermission('gestionar_personal');
        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:30'],
            'apellidos' => ['required', 'string', 'max:30'],
            'ci' => ['nullable', 'string', 'max:20', 'unique:personal,ci,'.$personal->getKey().',id_personal'],
            'telefono' => ['nullable', 'string', 'max:15'],
            'activo' => ['sometimes', 'boolean'],
        ]);
        $data['modificado_por'] = auth()->id();
        $personal->update($data);

        return response()->json($personal->fresh());
    }

    public function destroy(Personal $personal)
    {
        $this->authorizePermission('gestionar_personal');
        $personal->eliminado_por = auth()->id();
        $personal->save();
        $personal->delete();

        return response()->json(['message' => 'Personal eliminado lógicamente.']);
    }
}
