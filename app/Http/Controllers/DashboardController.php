<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Categoria;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Sucursal;

class DashboardController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('visualizar_panel');
        $fecha = $request->date('fecha') ?? now();
        $query = DB::table('ventas as v')->whereDate('v.fecha_venta', $fecha);
        if (! auth()->user()->roles()->where('nombre', 'Administrador')->exists()) {
            $query->whereIn('v.id_sucursal', auth()->user()->sucursales()->pluck('sucursales.id_sucursal'));
        }
        $summary = (clone $query)->selectRaw('COUNT(*) cantidad_ventas, COALESCE(SUM(total), 0) ingresos')->first();
        $byBranch = (clone $query)->join('sucursales as s', 's.id_sucursal', '=', 'v.id_sucursal')->select('s.nombre')->selectRaw('COUNT(*) cantidad, COALESCE(SUM(v.total), 0) total')->groupBy('s.id_sucursal', 's.nombre')->get();
        $lowStock = DB::table('producto_sucursal as ps')->join('productos as p', 'p.id_producto', '=', 'ps.id_producto')->whereColumn('ps.stock_actual', '<=', 'ps.stock_minimo')->get();
        if ($request->expectsJson()) return response()->json(['resumen' => $summary, 'ventas_por_sucursal' => $byBranch, 'stock_bajo' => $lowStock]);
        $sucursales = auth()->user()->roles()->where('nombre', 'Administrador')->exists()
            ? Sucursal::where('activo', true)->get() : auth()->user()->sucursales()->where('activo', true)->get();
        $activeBranch = session('sucursal_activa_id');
        $products = Producto::with('categoria')->where('activo', true)->whereHas('sucursales', function ($q) use ($activeBranch) {
            $q->where('sucursales.id_sucursal', $activeBranch);
        })->get();
        return view('dashboard', compact('summary', 'byBranch', 'lowStock', 'sucursales', 'products'));
    }
}
