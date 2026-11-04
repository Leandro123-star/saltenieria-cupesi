<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_productos');
        $producto = Producto::query()
            ->with('categoria')
            ->when($request->filled('buscar'), fn ($q) => $q->where('nombre', 'ilike', '%'.$request->string('buscar')->toString().'%'))
            ->when($request->filled('id_categoria'), fn ($q) => $q->where('id_categoria', $request->integer('id_categoria')))
            ->paginate(15);
        return response()->json($producto);
    }

    public function create()
    {
        $this->authorizePermission('gestionar_productos');
        return response()->json(['categorias' => Categoria::where('activo', true)->get(), 'message' => 'Formulario pendiente.']);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_productos');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'precio' => ['required', 'numeric', 'gt:0'],
            'activo' => ['required', 'boolean'],
            'id_categoria' => ['required', 'integer', 'exists:categorias,id_categoria'],
        ]);
        $data['creado_por'] = auth()->id();
        return response()->json(Producto::create($data), 201);
    }

    public function show(Producto $producto)
    {
        $this->authorizePermission('gestionar_productos');
        return response()->json($producto->load(['categoria', 'sucursales']));
    }

    public function edit(Producto $producto)
    {
        return $this->show($producto);
    }

    public function update(Request $request, Producto $producto)
    {
        $this->authorizePermission('gestionar_productos');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'precio' => ['required', 'numeric', 'gt:0'],
            'activo' => ['required', 'boolean'],
            'id_categoria' => ['required', 'integer', 'exists:categorias,id_categoria'],
        ]);
        $data['modificado_por'] = auth()->id();
        $producto->update($data);
        return response()->json($producto->fresh());
    }

    public function destroy(Producto $producto)
    {
        $this->authorizePermission('gestionar_productos');
        abort_if($producto->detallesVenta()->exists(), 422, 'No se puede eliminar un producto con historial de ventas.');
        $producto->eliminado_por = auth()->id();
        $producto->save();
        $producto->delete();
        return response()->json(['message' => 'Producto eliminado lógicamente.']);
    }
}
