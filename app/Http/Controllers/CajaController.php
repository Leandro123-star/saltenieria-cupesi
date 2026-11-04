<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Caja;
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
        if (! auth()->user()->roles()->where('nombre', 'Administrador')->exists()) {
            $query->whereIn('id_sucursal', auth()->user()->sucursales()->pluck('sucursales.id_sucursal'));
        }
        return response()->json($query->latest('fecha_apertura')->paginate(20));
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
        return response()->json($caja, 201);
    }

    public function cerrar(Request $request, Caja $caja)
    {
        $this->authorizePermission('gestionar_caja');
        $this->authorizeSucursal($caja->id_sucursal);
        $data = $request->validate(['efectivo_contado' => ['required', 'numeric', 'min:0']]);
        $caja = DB::transaction(function () use ($caja, $data): Caja {
            $caja = Caja::whereKey($caja->getKey())->lockForUpdate()->firstOrFail();
            if ($caja->estado !== 'ABIERTA') throw ValidationException::withMessages(['caja' => 'La caja ya está cerrada.']);
            $efectivo = DB::table('venta_pago as vp')
                ->join('ventas as v', 'v.id_venta', '=', 'vp.id_venta')
                ->join('metodos_pago as mp', 'mp.id_metodo_pago', '=', 'vp.id_metodo_pago')
                ->where('v.id_caja', $caja->getKey())
                ->whereRaw('LOWER(mp.nombre) = ?', ['efectivo'])
                ->sum('vp.importe');
            $esperado = (float) $caja->monto_inicial + (float) $efectivo;
            $caja->update([
                'fecha_cierre' => now(),
                'efectivo_contado' => $data['efectivo_contado'],
                'efectivo_esperado' => $esperado,
                'diferencia' => (float) $data['efectivo_contado'] - $esperado,
                'estado' => 'CERRADA',
                'modificado_por' => auth()->id(),
                'id_usuario_cierre' => auth()->id(),
            ]);
            return $caja->fresh();
        });
        return response()->json($caja);
    }

    public function estado(Request $request)
    {
        $this->authorizePermission('gestionar_caja');
        $data = $request->validate(['id_sucursal' => ['required', 'integer', 'exists:sucursales,id_sucursal']]);
        $this->authorizeSucursal($data['id_sucursal']);
        return response()->json(Caja::where('id_sucursal', $data['id_sucursal'])->where('estado', 'ABIERTA')->first());
    }
}
