<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\Request;
use App\Models\Categoria;
use App\Models\MetodoPago;
use App\Models\Producto;

class VentaController extends Controller
{
    use AuthorizesSistema;

    public function __construct(private readonly VentaService $ventaService)
    {
    }

    public function index(Request $request)
    {
        $this->authorizePermission('registrar_ventas');
        $query = Venta::with(['usuario', 'sucursal', 'caja'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_venta', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_venta', '<=', $request->date('hasta')))
            ->when($request->filled('id_sucursal'), function ($q) use ($request) {
                $idSucursal = $request->integer('id_sucursal');
                $this->authorizeSucursal($idSucursal);
                $q->where('id_sucursal', $idSucursal);
            });

        $allowedBranches = $this->activeSucursalIds();
        if (! auth()->user()->roles()->where('nombre', 'Administrador')->exists() || $allowedBranches->isNotEmpty()) {
            $query->whereIn('id_sucursal', $allowedBranches);
        }

        $ventas = $query->latest('fecha_venta')->paginate(20)->withQueryString();
        return $request->expectsJson() ? response()->json($ventas) : view('ventas.index', compact('ventas'));
    }

    public function create()
    {
        $this->authorizePermission('registrar_ventas');
        $branchId = session('sucursal_activa_id');
        $productos = Producto::with('categoria')->where('activo', true)
            ->whereHas('sucursales', fn ($q) => $q->where('sucursales.id_sucursal', $branchId))->get();
        $categorias = Categoria::where('activo', true)->orderBy('nombre')->get();
        $metodos = MetodoPago::where('activo', true)->orderBy('nombre')->get();
        $cajaAbierta = \App\Models\Caja::where('id_sucursal', $branchId)->where('estado', 'ABIERTA')->exists();
        $productosData = $productos->map(fn ($producto) => [
            'id' => $producto->id_producto,
            'name' => $producto->nombre,
            'price' => (float) $producto->precio,
            'category' => $producto->id_categoria,
            'stock' => (int) ($producto->sucursales->firstWhere('id_sucursal', $branchId)?->pivot->stock_actual ?? 0),
            'photo' => $producto->foto,
        ])->values()->all();
        $metodosData = $metodos->map(fn ($metodo) => ['id' => $metodo->id_metodo_pago, 'name' => $metodo->nombre])->values()->all();
        return view('ventas.create', compact('productosData', 'metodosData', 'categorias', 'cajaAbierta', 'branchId'));
    }

    public function store(Request $request)
    {
        $this->authorizePermission('registrar_ventas');
        $data = $request->validate([
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id_sucursal'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_producto' => ['required', 'integer', 'distinct', 'exists:productos,id_producto'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'pagos' => ['required', 'array', 'min:1'],
            'pagos.*.id_metodo_pago' => ['required', 'integer', 'exists:metodos_pago,id_metodo_pago'],
            'pagos.*.importe' => ['required', 'numeric', 'gt:0'],
            'pagos.*.verificado' => ['sometimes', 'boolean'],
        ]);
        $this->authorizeSucursal($data['id_sucursal']);
        $venta = $this->ventaService->registrar($data, auth()->id());
        return $request->expectsJson() ? response()->json($venta, 201) : redirect()->route('ventas.show', $venta)->with('success', 'Venta registrada correctamente.');
    }

    public function show(Venta $venta)
    {
        $this->authorizePermission('registrar_ventas');
        $this->authorizeSucursal($venta->id_sucursal);
        $venta->load(['usuario', 'sucursal', 'caja', 'detalles.producto', 'pagos.metodoPago']);
        return request()->expectsJson() ? response()->json($venta) : view('ventas.show', compact('venta'));
    }

    public function receipt(Venta $venta)
    {
        $this->authorizePermission('registrar_ventas');
        $this->authorizeSucursal($venta->id_sucursal);
        $venta->load(['usuario', 'sucursal', 'detalles.producto', 'pagos.metodoPago']);
        return view('ventas.receipt', compact('venta'));
    }
}
