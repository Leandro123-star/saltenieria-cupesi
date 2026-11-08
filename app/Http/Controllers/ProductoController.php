<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        return $request->expectsJson() ? response()->json($producto) : view('productos.index', [
            'productos' => $producto,
            'categorias' => Categoria::orderBy('nombre')->get(),
            'sucursales' => auth()->user()->roles()->where('nombre', 'Administrador')->exists()
                ? \App\Models\Sucursal::where('activo', true)->get()
                : auth()->user()->sucursales()->where('activo', true)->get(),
        ]);
    }

    public function create()
    {
        $this->authorizePermission('gestionar_productos');
        return view('productos.form', [
            'producto' => new Producto,
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_productos');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'precio' => ['required', 'numeric', 'gt:0'],
            'activo' => ['required', 'boolean'],
            'id_categoria' => ['required', 'integer', 'exists:categorias,id_categoria'],
        ]);
        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('productos', 'public');
        }
        $data['creado_por'] = auth()->id();
        $producto = Producto::create($data);
        return $request->expectsJson() ? response()->json($producto, 201) : redirect()->route('productos.index')->with('success', 'Producto registrado.');
    }

    public function show(Producto $producto)
    {
        $this->authorizePermission('gestionar_productos');
        return request()->expectsJson() ? response()->json($producto->load(['categoria', 'sucursales'])) : view('productos.form', [
            'producto' => $producto,
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(),
        ]);
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
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'precio' => ['required', 'numeric', 'gt:0'],
            'activo' => ['required', 'boolean'],
            'id_categoria' => ['required', 'integer', 'exists:categorias,id_categoria'],
        ]);
        if ($request->hasFile('foto')) {
            if ($producto->foto) Storage::disk('public')->delete($producto->foto);
            $data['foto'] = $request->file('foto')->store('productos', 'public');
        } else {
            unset($data['foto']);
        }
        $data['modificado_por'] = auth()->id();
        $producto->update($data);
        return $request->expectsJson() ? response()->json($producto->fresh()) : redirect()->route('productos.index')->with('success', 'Producto actualizado.');
    }

    public function destroy(Producto $producto)
    {
        $this->authorizePermission('gestionar_productos');
        abort_if($producto->detallesVenta()->exists(), 422, 'No se puede eliminar un producto con historial de ventas.');
        $producto->eliminado_por = auth()->id();
        $producto->save();
        $producto->delete();
        return request()->expectsJson() ? response()->json(['message' => 'Producto eliminado lógicamente.']) : redirect()->route('productos.index')->with('success', 'Producto desactivado.');
    }

    public function updateStock(Request $request, Producto $producto)
    {
        $this->authorizePermission('gestionar_productos');
        $data = $request->validate([
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id_sucursal'],
            'stock_actual' => ['required', 'integer', 'min:0'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
        ]);
        $this->authorizeSucursal($data['id_sucursal']);
        DB::table('producto_sucursal')->updateOrInsert(
            ['id_producto' => $producto->getKey(), 'id_sucursal' => $data['id_sucursal']],
            ['stock_actual' => $data['stock_actual'], 'stock_minimo' => $data['stock_minimo']]
        );
        return redirect()->route('productos.index')->with('success', 'Stock actualizado.');
    }
}
