<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\Request;

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

        if (! auth()->user()->roles()->where('nombre', 'Administrador')->exists()) {
            $query->whereIn('id_sucursal', auth()->user()->sucursales()->pluck('sucursales.id_sucursal'));
        }

        return response()->json($query->latest('fecha_venta')->paginate(20));
    }

    public function create()
    {
        $this->authorizePermission('registrar_ventas');
        return response()->json(['message' => 'Formulario de venta pendiente.']);
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
        return response()->json($this->ventaService->registrar($data, auth()->id()), 201);
    }

    public function show(Venta $venta)
    {
        $this->authorizePermission('registrar_ventas');
        $this->authorizeSucursal($venta->id_sucursal);
        return response()->json($venta->load(['usuario', 'sucursal', 'caja', 'detalles.producto', 'pagos.metodoPago']));
    }
}
