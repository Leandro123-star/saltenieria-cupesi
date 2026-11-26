<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    use AuthorizesSistema;

    public function __construct(private readonly VentaService $ventaService) {}

    public function index(Request $request)
    {
        $this->authorizePermission('registrar_ventas');
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);
        $query = Venta::with(['usuario', 'sucursal', 'caja'])
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_venta', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_venta', '<=', $request->date('hasta')))
            ->when($request->filled('id_sucursal'), function ($q) use ($request) {
                $idSucursal = $request->integer('id_sucursal');
                $this->authorizeSucursal($idSucursal);
                $q->where('id_sucursal', $idSucursal);
            });

        $allowedBranches = $this->activeSucursalIds();
        if (! auth()->user()->esAdministrador() || $allowedBranches->isNotEmpty()) {
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
        $cajaAbierta = Caja::where('id_sucursal', $branchId)->where('estado', 'ABIERTA')->exists();
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
        $request->session()->flash('success', 'Venta registrada correctamente.');

        return $request->expectsJson()
            ? response()->json($venta, 201)
            : redirect()->route('ventas.index');
    }

    public function show(Venta $venta)
    {
        $this->authorizePermission('registrar_ventas');
        $this->authorizeSucursal($venta->id_sucursal);
        $venta->load(['usuario', 'sucursal', 'caja', 'detalles.producto', 'pagos.metodoPago']);
        $puedeCorregir = $this->puedeCorregir($venta);

        return request()->expectsJson()
            ? response()->json($venta->setAttribute('puede_corregir', $puedeCorregir))
            : view('ventas.show', compact('venta', 'puedeCorregir'));
    }

    public function corregir(Venta $venta)
    {
        $this->authorizePermission('registrar_ventas');
        $this->authorizeSucursal($venta->id_sucursal);
        $this->ensurePuedeCorregir($venta);

        $venta->load(['usuario', 'sucursal', 'caja', 'detalles.producto', 'pagos.metodoPago']);

        $productos = Producto::query()
            ->with(['categoria', 'sucursales' => fn ($q) => $q->where('sucursales.id_sucursal', $venta->id_sucursal)])
            ->where('activo', true)
            ->whereHas('sucursales', fn ($q) => $q->where('sucursales.id_sucursal', $venta->id_sucursal))
            ->get();

        $cantidadesOriginales = $venta->detalles->groupBy('id_producto')->map->sum('cantidad');
        $productosData = $productos->map(function (Producto $producto) use ($venta, $cantidadesOriginales): array {
            $stock = (int) ($producto->sucursales->firstWhere('id_sucursal', $venta->id_sucursal)?->pivot->stock_actual ?? 0);

            return [
                'id' => $producto->id_producto,
                'name' => $producto->nombre,
                'price' => (float) $producto->precio,
                'stock' => $stock + (int) ($cantidadesOriginales[$producto->id_producto] ?? 0),
            ];
        })->values()->all();

        $metodos = MetodoPago::query()
            ->where(function ($q) use ($venta): void {
                $q->where('activo', true)
                    ->orWhereIn('id_metodo_pago', $venta->pagos->pluck('id_metodo_pago'));
            })
            ->orderBy('nombre')
            ->get();

        $metodosData = $metodos->map(fn (MetodoPago $metodo): array => [
            'id' => $metodo->id_metodo_pago,
            'name' => $metodo->nombre,
        ])->values()->all();

        $detallesData = $venta->detalles->map(fn ($detalle): array => [
            'id_detalle_venta' => (int) $detalle->id_detalle_venta,
            'id_producto' => (int) $detalle->id_producto,
            'nombre' => $detalle->producto?->nombre,
            'cantidad' => (int) $detalle->cantidad,
            'precio_unitario' => (float) $detalle->precio_unitario,
        ])->values()->all();

        $pagosData = $venta->pagos->map(fn ($pago): array => [
            'id_metodo_pago' => (int) $pago->id_metodo_pago,
            'importe' => (float) $pago->importe,
            'verificado' => (bool) $pago->verificado,
        ])->values()->all();

        return view('ventas.corregir', compact(
            'venta',
            'productosData',
            'metodosData',
            'detallesData',
            'pagosData'
        ));
    }

    public function actualizarCorreccion(Request $request, Venta $venta)
    {
        $this->authorizePermission('registrar_ventas');
        $this->authorizeSucursal($venta->id_sucursal);
        $this->ensurePuedeCorregir($venta);

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
            'declaracion' => ['accepted'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_detalle_venta' => ['required', 'integer', 'distinct'],
            'detalles.*.id_producto' => ['required', 'integer', 'exists:productos,id_producto'],
            // Los pagos solo se envían cuando la sustitución cambia el total y
            // el usuario ajusta los importes; si no llegan, se conservan los de la BD.
            'pagos' => ['sometimes', 'array', 'min:1'],
            'pagos.*.id_metodo_pago' => ['required', 'integer', 'exists:metodos_pago,id_metodo_pago'],
            'pagos.*.importe' => ['required', 'numeric', 'gt:0'],
            'pagos.*.verificado' => ['sometimes', 'boolean'],
        ], [
            'detalles.required' => 'La venta debe conservar todos sus productos.',
            'detalles.*.id_producto.required' => 'Seleccione el producto de cada línea.',
            'detalles.*.id_producto.exists' => 'El producto seleccionado no existe.',
            'pagos.min' => 'Debe registrar al menos un pago.',
            'pagos.*.id_metodo_pago.required' => 'Seleccione el método de cada pago.',
            'pagos.*.id_metodo_pago.exists' => 'El método de pago seleccionado no existe.',
            'pagos.*.importe.required' => 'Indique el importe de cada pago ajustado.',
            'pagos.*.importe.numeric' => 'El importe de cada pago debe ser un número.',
            'pagos.*.importe.gt' => 'El importe de cada pago debe ser mayor que cero.',
            'motivo.required' => 'Debe indicar el motivo de la corrección.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
            'declaracion.accepted' => 'Debe confirmar que la corrección se debe a un error de registro.',
        ]);

        $this->ventaService->corregir($venta, $data, $request->user());

        return redirect()->route('ventas.show', $venta)->with('success', 'Venta corregida correctamente.');
    }

    public function receipt(Venta $venta)
    {
        $this->authorizePermission('registrar_ventas');
        $this->authorizeSucursal($venta->id_sucursal);
        $venta->load(['usuario', 'sucursal', 'detalles.producto', 'pagos.metodoPago']);

        return view('ventas.receipt', compact('venta'));
    }

    private function puedeCorregir(Venta $venta): bool
    {
        if ($venta->estado !== 'CONFIRMADA' || $venta->caja?->estado !== 'ABIERTA') {
            return false;
        }
        if (! $this->canUseSucursal($venta->id_sucursal)) {
            return false;
        }

        return auth()->user()->esAdministrador()
            || (int) $venta->id_usuario === (int) auth()->id();
    }

    private function ensurePuedeCorregir(Venta $venta): void
    {
        abort_unless($venta->estado === 'CONFIRMADA', 403, 'Solo se pueden corregir ventas confirmadas.');
        abort_unless(
            $venta->caja?->estado === 'ABIERTA',
            403,
            'La caja de la venta ya está cerrada; la venta se considera entregada y no puede corregirse.'
        );

        if (! auth()->user()->esAdministrador()) {
            abort_unless(
                (int) $venta->id_usuario === (int) auth()->id(),
                403,
                'Solo puede corregir sus propias ventas.'
            );
        }
    }
}
