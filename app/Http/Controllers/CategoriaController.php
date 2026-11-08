<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_categorias');
        $categorias = Categoria::withCount('productos')
            ->when($request->filled('buscar'), fn ($q) => $q->where('nombre', 'ilike', '%'.$request->string('buscar')->toString().'%'))
            ->orderBy('nombre')->paginate(15)->withQueryString();
        return $request->expectsJson() ? response()->json($categorias) : view('categorias.index', compact('categorias'));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_categorias');
        return view('categorias.form', ['categoria' => new Categoria]);
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
        $categoria = Categoria::create($data);
        return $request->expectsJson() ? response()->json($categoria, 201) : redirect()->route('categorias.index')->with('success', 'Categoría registrada.');
    }

    public function show(Categoria $categoria)
    {
        $this->authorizePermission('gestionar_categorias');
        return request()->expectsJson() ? response()->json($categoria->load('productos')) : view('categorias.form', compact('categoria'));
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
        return $request->expectsJson() ? response()->json($categoria->fresh()) : redirect()->route('categorias.index')->with('success', 'Categoría actualizada.');
    }

    public function destroy(Categoria $categoria)
    {
        $this->authorizePermission('gestionar_categorias');
        abort_if($categoria->productos()->exists(), 422, 'No se puede eliminar una categoría con productos relacionados.');
        $categoria->eliminado_por = auth()->id();
        $categoria->save();
        $categoria->delete();
        return request()->expectsJson() ? response()->json(['message' => 'Categoría eliminada lógicamente.']) : redirect()->route('categorias.index')->with('success', 'Categoría desactivada.');
    }

    public function restore(Request $request)
    {
        $this->authorizePermission('gestionar_categorias');
        $data = $request->validate(['nombre' => ['required', 'string', 'max:30']]);
        $categoria = Categoria::withTrashed()->where('nombre', $data['nombre'])->firstOrFail();
        $categoria->restore();
        $categoria->update(['activo' => true, 'modificado_por' => auth()->id()]);
        return redirect()->route('categorias.index')->with('success', 'Categoría reactivada.');
    }
}
