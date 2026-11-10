<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Caja;
use App\Support\CajaCalculadora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CajaController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_caja');
        $query = Caja::with(['sucursal', 'usuarioApertura', 'usuarioCierre'])
            ->when($request->filled('id_sucursal'), function ($q) use ($request) {
                $idSucursal = $request->integer('id_sucursal');
                $this->authorizeSucursal($idSucursal);
                $q->where('id_sucursal', $idSucursal);
            });
        $allowedBranches = $this->activeSucursalIds();
        if (! auth()->user()->roles()->where('nombre', 'Administrador')->exists() || $allowedBranches->isNotEmpty()) {
            $query->whereIn('id_sucursal', $allowedBranches);
        }
        $cajas = $query->latest('fecha_apertura')->paginate(20)->withQueryString();
        return $request->expectsJson() ? response()->json($cajas) : view('cajas.index', [
            'cajas' => $cajas,
            'sucursales' => auth()->user()->roles()->where('nombre', 'Administrador')->exists()
                ? \App\Models\Sucursal::where('activo', true)->get()
                : auth()->user()->sucursales()->where('activo', true)->get(),
        ]);
    }

    public function show(Caja $caja)
    {
        $this->authorizePermission('gestionar_caja');
        $this->authorizeSucursal($caja->id_sucursal);
        return response()->json($caja->load(['sucursal', 'usuarioApertura', 'usuarioCierre', 'ventas.pagos.metodoPago']));
    }

    public function abrir(Request $request)
    {
        $this->authorizePermission('gestionar_caja');
        $data = $request->validate([
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id_sucursal'],
            'monto_inicial' => ['required', 'numeric', 'min:0'],
        ]);
        $this->authorizeSucursal($data['id_sucursal']);
        $caja = DB::transaction(function () use ($data): Caja {
            $exists = Caja::where('id_sucursal', $data['id_sucursal'])
                ->where('estado', 'ABIERTA')->lockForUpdate()->exists();
            if ($exists) throw ValidationException::withMessages(['id_sucursal' => 'Ya existe una caja abierta en esta sucursal.']);
            return Caja::create([
                'fecha_apertura' => now(),
                'monto_inicial' => $data['monto_inicial'],
                'estado' => 'ABIERTA',
                'id_sucursal' => $data['id_sucursal'],
                'id_usuario_apertura' => auth()->id(),
                'creado_por' => auth()->id(),
            ]);
        });
        return $request->expectsJson() ? response()->json($caja, 201) : redirect()->route('cajas.index')->with('success', 'Caja abierta correctamente.');
    }

    public function cerrar(Request $request, Caja $caja)
    {
        $this->authorizePermission('gestionar_caja');
        $this->authorizeSucursal($caja->id_sucursal);
        $data = $request->validate(['efectivo_contado' => ['required', 'numeric', 'min:0']]);
        $caja = DB::transaction(function () use ($caja, $data): Caja {
            $caja = Caja::whereKey($caja->getKey())->lockForUpdate()->firstOrFail();
            if ($caja->estado !== 'ABIERTA') throw ValidationException::withMessages(['caja' => 'La caja ya está cerrada.']);
            $pagos = DB::table('venta_pago as vp')
                ->join('ventas as v', 'v.id_venta', '=', 'vp.id_venta')
                ->join('metodos_pago as mp', 'mp.id_metodo_pago', '=', 'vp.id_metodo_pago')
                ->where('v.id_caja', $caja->getKey())
                ->select('mp.nombre as metodo', 'vp.importe')
                ->get();
            $esperadoCentavos = CajaCalculadora::efectivoEsperado($caja->monto_inicial, $pagos->all());
            $caja->update([
                'fecha_cierre' => now(),
                'efectivo_contado' => $data['efectivo_contado'],
                'efectivo_esperado' => number_format($esperadoCentavos / 100, 2, '.', ''),
                'diferencia' => number_format(CajaCalculadora::diferencia($data['efectivo_contado'], $esperadoCentavos) / 100, 2, '.', ''),
                'estado' => 'CERRADA',
                'modificado_por' => auth()->id(),
                'id_usuario_cierre' => auth()->id(),
            ]);
            return $caja->fresh();
        });
        return $request->expectsJson() ? response()->json($caja) : redirect()->route('cajas.index')->with('success', 'Caja cerrada correctamente.');
    }

    public function estado(Request $request)
    {
        $this->authorizePermission('gestionar_caja');
        $data = $request->validate(['id_sucursal' => ['required', 'integer', 'exists:sucursales,id_sucursal']]);
        $this->authorizeSucursal($data['id_sucursal']);
        return response()->json(Caja::where('id_sucursal', $data['id_sucursal'])->where('estado', 'ABIERTA')->first());
    }
}
