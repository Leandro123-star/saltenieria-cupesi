<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    use AuthorizesSistema;

    public function index()
    {
        $this->authorizePermission('gestionar_categorias');
        return response()->json(Categoria::withCount('productos')->paginate(15));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_categorias');
        return response()->json(['message' => 'Formulario de categoría pendiente.']);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_categorias');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:30', 'unique:categorias,nombre'],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'activo' => ['required', 'boolean'],
        ]);
        $data['creado_por'] = auth()->id();
        return response()->json(Categoria::create($data), 201);
    }

    public function show(Categoria $categoria)
    {
        $this->authorizePermission('gestionar_categorias');
        return response()->json($categoria->load('productos'));
    }

    public function edit(Categoria $categoria)
    {
        return $this->show($categoria);
    }

    public function update(Request $request, Categoria $categoria)
    {
        $this->authorizePermission('gestionar_categorias');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:30', 'unique:categorias,nombre,'.$categoria->getKey().',id_categoria'],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'activo' => ['required', 'boolean'],
        ]);
        $data['modificado_por'] = auth()->id();
        $categoria->update($data);
        return response()->json($categoria->fresh());
    }

    public function destroy(Categoria $categoria)
    {
        $this->authorizePermission('gestionar_categorias');
        abort_if($categoria->productos()->exists(), 422, 'No se puede eliminar una categoría con productos relacionados.');
        $categoria->eliminado_por = auth()->id();
        $categoria->save();
        $categoria->delete();
        return response()->json(['message' => 'Categoría eliminada lógicamente.']);
    }
}
