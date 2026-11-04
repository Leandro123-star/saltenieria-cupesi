<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('visualizar_panel');
        $query = DB::table('ventas as v')->whereDate('v.fecha_venta', $request->date('fecha', now()));
        if (! auth()->user()->roles()->where('nombre', 'Administrador')->exists()) {
            $query->whereIn('v.id_sucursal', auth()->user()->sucursales()->pluck('sucursales.id_sucursal'));
        }
        $summary = (clone $query)->selectRaw('COUNT(*) cantidad_ventas, COALESCE(SUM(total), 0) ingresos')->first();
        $byBranch = (clone $query)->join('sucursales as s', 's.id_sucursal', '=', 'v.id_sucursal')->select('s.nombre')->selectRaw('COUNT(*) cantidad, COALESCE(SUM(v.total), 0) total')->groupBy('s.id_sucursal', 's.nombre')->get();
        $lowStock = DB::table('producto_sucursal as ps')->join('productos as p', 'p.id_producto', '=', 'ps.id_producto')->whereColumn('ps.stock_actual', '<=', 'ps.stock_minimo')->get();
        return response()->json(['resumen' => $summary, 'ventas_por_sucursal' => $byBranch, 'stock_bajo' => $lowStock]);
    }
}
