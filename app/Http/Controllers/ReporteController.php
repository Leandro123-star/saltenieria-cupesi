<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    use AuthorizesSistema;

    private function ventasQuery(Request $request)
    {
        $query = DB::table('ventas as v')
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('v.fecha_venta', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('v.fecha_venta', '<=', $request->date('hasta')))
            ->when($request->filled('id_sucursal'), fn ($q) => $q->where('v.id_sucursal', $request->integer('id_sucursal')));
        $allowedBranches = $this->activeSucursalIds();
        if (! auth()->user()->roles()->where('nombre', 'Administrador')->exists() || $allowedBranches->isNotEmpty()) {
            $query->whereIn('v.id_sucursal', $allowedBranches);
        }
        return $query;
    }

    public function ventasPorPeriodo(Request $request)
    {
        $this->authorizePermission('generar_reportes');
        return response()->json($this->ventasQuery($request)->selectRaw('DATE(v.fecha_venta) fecha, COUNT(*) cantidad, SUM(v.total) total')->groupByRaw('DATE(v.fecha_venta)')->orderBy('fecha')->get());
    }

    public function ventasPorSucursal(Request $request)
    {
        $this->authorizePermission('generar_reportes');
        return response()->json($this->ventasQuery($request)->join('sucursales as s', 's.id_sucursal', '=', 'v.id_sucursal')->select('s.nombre')->selectRaw('COUNT(*) cantidad, SUM(v.total) total')->groupBy('s.id_sucursal', 's.nombre')->get());
    }

    public function productosMasVendidos(Request $request)
    {
        $this->authorizePermission('generar_reportes');
        return response()->json($this->ventasQuery($request)->join('detalle_venta as dv', 'dv.id_venta', '=', 'v.id_venta')->join('productos as p', 'p.id_producto', '=', 'dv.id_producto')->select('p.id_producto', 'p.nombre')->selectRaw('SUM(dv.cantidad) cantidad')->groupBy('p.id_producto', 'p.nombre')->orderByDesc('cantidad')->get());
    }

    public function existencias(Request $request)
    {
        $this->authorizePermission('consultar_inventario');
        $idSucursal = $request->integer('id_sucursal');
        if ($idSucursal) $this->authorizeSucursal($idSucursal);
        return response()->json(DB::table('producto_sucursal as ps')->join('productos as p', 'p.id_producto', '=', 'ps.id_producto')->when($idSucursal, fn ($q) => $q->where('ps.id_sucursal', $idSucursal))->get());
    }

    public function pagos(Request $request)
    {
        $this->authorizePermission('generar_reportes');
        return response()->json($this->ventasQuery($request)->join('venta_pago as vp', 'vp.id_venta', '=', 'v.id_venta')->join('metodos_pago as mp', 'mp.id_metodo_pago', '=', 'vp.id_metodo_pago')->select('mp.nombre')->selectRaw('SUM(vp.importe) total')->groupBy('mp.id_metodo_pago', 'mp.nombre')->get());
    }

    public function cajas(Request $request)
    {
        $this->authorizePermission('generar_reportes');
        $query = DB::table('cajas as c')->join('sucursales as s', 's.id_sucursal', '=', 'c.id_sucursal')->when($request->filled('id_sucursal'), fn ($q) => $q->where('c.id_sucursal', $request->integer('id_sucursal')));
        return response()->json($query->select('c.*', 's.nombre as sucursal')->orderByDesc('c.fecha_apertura')->paginate(20));
    }
}
