<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('consultar_inventario');
        $idSucursal = $request->integer('id_sucursal');
        if ($idSucursal) $this->authorizeSucursal($idSucursal);

        $inventory = DB::table('producto_sucursal as ps')
            ->join('productos as p', 'p.id_producto', '=', 'ps.id_producto')
            ->join('sucursales as s', 's.id_sucursal', '=', 'ps.id_sucursal')
            ->select('ps.*', 'p.nombre as producto', 'p.precio', 's.nombre as sucursal')
            ->when($idSucursal, fn ($q) => $q->where('ps.id_sucursal', $idSucursal))
            ->when(! auth()->user()->roles()->where('nombre', 'Administrador')->exists(), function ($q) {
                $q->whereIn('ps.id_sucursal', auth()->user()->sucursales()->pluck('sucursales.id_sucursal'));
            })
            ->orderBy('p.nombre')->paginate(20);

        return response()->json($inventory);
    }

    public function showProducto(Producto $producto, Request $request)
    {
        $this->authorizePermission('consultar_inventario');
        $idSucursal = $request->integer('id_sucursal');
        $query = DB::table('producto_sucursal as ps')
            ->join('sucursales as s', 's.id_sucursal', '=', 'ps.id_sucursal')
            ->where('ps.id_producto', $producto->getKey());
        if ($idSucursal) {
            $this->authorizeSucursal($idSucursal);
            $query->where('ps.id_sucursal', $idSucursal);
        }
        return response()->json($query->get());
    }

    public function stockBajo(Request $request)
    {
        $this->authorizePermission('consultar_inventario');
        $idSucursal = $request->integer('id_sucursal');
        if ($idSucursal) $this->authorizeSucursal($idSucursal);
        $rows = DB::table('producto_sucursal as ps')
            ->join('productos as p', 'p.id_producto', '=', 'ps.id_producto')
            ->whereColumn('ps.stock_actual', '<=', 'ps.stock_minimo')
            ->when($idSucursal, fn ($q) => $q->where('ps.id_sucursal', $idSucursal))
            ->get();
        return response()->json($rows);
    }

    public function updateStockMinimo(Request $request, Producto $producto)
    {
        $this->authorizePermission('gestionar_productos');
        $data = $request->validate([
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id_sucursal'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
        ]);
        $this->authorizeSucursal($data['id_sucursal']);
        DB::table('producto_sucursal')
            ->where('id_producto', $producto->getKey())
            ->where('id_sucursal', $data['id_sucursal'])
            ->update(['stock_minimo' => $data['stock_minimo']]);
        return response()->json(['message' => 'Stock mínimo actualizado.']);
    }
}
