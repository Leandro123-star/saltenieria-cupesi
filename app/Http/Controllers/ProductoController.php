<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Support\Formatos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();
        return $request->expectsJson() ? response()->json($producto) : view('productos.index', [
            'productos' => $producto,
            'categorias' => Categoria::orderBy('nombre')->get(),
            'sucursales' => auth()->user()->sucursalesAutorizadas()->orderBy('nombre')->get(),
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
            'nombre' => ['required', 'string', 'max:50', 'regex:'.Formatos::NOMBRE_COMERCIAL],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'precio' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
            'activo' => ['required', 'boolean'],
            'id_categoria' => ['required', 'integer', Rule::exists('categorias', 'id_categoria')->where('activo', true)],
        ], [
            'id_categoria.exists' => 'La categoría seleccionada no está activa.',
        ] + $this->mensajes());
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
        $producto->load(['categoria', 'sucursales']);
        if (request()->expectsJson()) {
            return response()->json($producto);
        }
        return view('productos.show', [
            'producto' => $producto,
            'sucursales' => auth()->user()->sucursalesAutorizadas()->orderBy('nombre')->get(),
        ]);
    }

    public function edit(Producto $producto)
    {
        $this->authorizePermission('gestionar_productos');
        return view('productos.form', [
            'producto' => $producto,
            // Activas + la categoría actual del producto (aunque esté inactiva) para conservarla.
            'categorias' => Categoria::where('activo', true)
                ->orWhere('id_categoria', $producto->id_categoria)
                ->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Producto $producto)
    {
        $this->authorizePermission('gestionar_productos');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'regex:'.Formatos::NOMBRE_COMERCIAL],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'precio' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
            'activo' => ['required', 'boolean'],
            'id_categoria' => ['required', 'integer', Rule::exists('categorias', 'id_categoria')->where(
                fn ($q) => $q->where('activo', true)->orWhere('id_categoria', $producto->id_categoria)
            )],
        ], [
            'id_categoria.exists' => 'La categoría seleccionada no está activa.',
        ] + $this->mensajes());
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
        if ($producto->detallesVenta()->exists()) {
            $mensaje = 'No se puede desactivar un producto con historial de ventas.';
            abort_if(request()->expectsJson(), 422, $mensaje);

            return redirect()->route('productos.index')->with('error', $mensaje);
        }
        $producto->update(['activo' => false, 'modificado_por' => auth()->id()]);
        return request()->expectsJson() ? response()->json(['message' => 'Producto desactivado.']) : redirect()->route('productos.index')->with('success', 'Producto desactivado.');
    }

    public function updateStock(Request $request, Producto $producto)
    {
        $this->authorizePermission('gestionar_productos');
        $data = $request->validate([
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id_sucursal'],
            'stock_actual' => ['required', 'integer', 'min:0', 'max:100000'],
            'stock_minimo' => ['required', 'integer', 'min:0', 'max:100000'],
        ], self::mensajesStock());
        $this->authorizeSucursal($data['id_sucursal']);
        DB::table('producto_sucursal')->updateOrInsert(
            ['id_producto' => $producto->getKey(), 'id_sucursal' => $data['id_sucursal']],
            ['stock_actual' => $data['stock_actual'], 'stock_minimo' => $data['stock_minimo']]
        );
        return redirect()->route('productos.index')->with('success', 'Stock actualizado.');
    }

    private function mensajes(): array
    {
        return [
            'nombre.regex' => 'El nombre del producto debe contener letras y solo puede incluir números, espacios y los signos . , \' & ( ) / % + -',
            'precio.numeric' => 'El precio debe ser un número mayor que cero.',
            'precio.gt' => 'El precio debe ser un número mayor que cero.',
            'precio.max' => 'El precio no puede superar 99 999,99.',
            'id_categoria.required' => 'Selecciona una categoría.',
            'foto.image' => 'La foto debe ser una imagen.',
            'foto.mimes' => 'La foto debe ser JPG, PNG o WEBP.',
            'foto.max' => 'La foto no debe pesar más de 2 MB.',
        ];
    }

    /** Mensajes de stock compartidos con InventarioController. */
    public static function mensajesStock(): array
    {
        $entero = 'La cantidad debe ser un número entero válido (0 o mayor).';

        return [
            'stock_actual.integer' => $entero,
            'stock_actual.min' => $entero,
            'stock_actual.max' => 'La cantidad no puede superar 100 000 unidades.',
            'stock_minimo.integer' => $entero,
            'stock_minimo.min' => $entero,
            'stock_minimo.max' => 'La cantidad no puede superar 100 000 unidades.',
        ];
    }
}
