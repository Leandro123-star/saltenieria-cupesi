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
        if (! $categoria->activo) {
            return request()->expectsJson()
                ? response()->json(['message' => 'La categoría ya está inactiva.'])
                : redirect()->route('categorias.index')->with('error', 'La categoría ya está inactiva.');
        }
        // Solo se marca como inactiva: los productos conservan su categoría.
        $categoria->update(['activo' => false, 'modificado_por' => auth()->id()]);
        return request()->expectsJson() ? response()->json(['message' => 'Categoría desactivada correctamente.']) : redirect()->route('categorias.index')->with('success', 'Categoría desactivada correctamente.');
    }

    public function restore(Categoria $categoria)
    {
        $this->authorizePermission('gestionar_categorias');
        if ($categoria->activo) {
            return request()->expectsJson()
                ? response()->json(['message' => 'La categoría ya está activa.'])
                : redirect()->route('categorias.index')->with('error', 'La categoría ya está activa.');
        }
        $categoria->update(['activo' => true, 'modificado_por' => auth()->id()]);
        return request()->expectsJson() ? response()->json(['message' => 'Categoría activada correctamente.']) : redirect()->route('categorias.index')->with('success', 'Categoría activada correctamente.');
    }
}
