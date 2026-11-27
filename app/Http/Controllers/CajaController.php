<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Caja;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Support\CajaCalculadora;
use App\Support\VentaCalculadora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CajaController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_caja');
        $query = Caja::with(['sucursal', 'usuarioApertura.persona', 'usuarioCierre'])
            ->when($request->filled('id_sucursal'), function ($q) use ($request) {
                $idSucursal = $request->integer('id_sucursal');
                $this->authorizeSucursal($idSucursal);
                $q->where('id_sucursal', $idSucursal);
            })
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_apertura', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_apertura', '<=', $request->date('hasta')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')->toString()));
        $allowedBranches = $this->activeSucursalIds();
        if ($this->limitarASucursalesAutorizadas()) {
            $query->whereIn('id_sucursal', $allowedBranches);
        }
        $cajas = $query->latest('fecha_apertura')->paginate(20)->withQueryString();
        return $request->expectsJson() ? response()->json($cajas) : view('cajas.index', [
            'cajas' => $cajas,
            'sucursales' => auth()->user()->sucursalesAutorizadas()->orderBy('nombre')->get(),
        ]);
    }

    public function show(Caja $caja)
    {
        $this->authorizeAnyPermission(['gestionar_caja', 'generar_reportes']);
        abort_unless(
            auth()->user()->esAdministrador(),
            403,
            'Solo el Administrador puede consultar el resultado de caja.'
        );
        $this->authorizeSucursal($caja->id_sucursal);
        $caja->load(['sucursal', 'usuarioApertura.persona', 'usuarioCierre']);

        $ventas = Venta::query()
            ->with('pagos.metodoPago')
            ->where('id_caja', $caja->getKey())
            ->where('estado', 'CONFIRMADA')
            ->latest('fecha_venta')
            ->get();

        $resultado = CajaCalculadora::resultado($caja, $ventas);

        if (request()->expectsJson()) {
            return response()->json($caja->setRelation('ventas', $ventas)->setAttribute('resultado', $resultado));
        }

        return view('cajas.show', compact('caja', 'resultado'));
    }

    public function abrir(Request $request)
    {
        $this->authorizePermission('gestionar_caja');
        $data = $request->validate([
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id_sucursal'],
            'monto_inicial' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ], [
            'monto_inicial.required' => 'Indica el monto inicial de la caja.',
            'monto_inicial.numeric' => 'El monto inicial debe ser un número (0 o mayor).',
            'monto_inicial.min' => 'El monto inicial no puede ser negativo.',
            'monto_inicial.max' => 'El monto inicial no puede superar 999 999,99.',
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
        $data = $request->validate([
            'efectivo_contado' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'qr_verificado' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ], [
            // Lo contado no puede ser negativo; las diferencias del arqueo
            // (contado - esperado) sí pueden serlo y se calculan aparte.
            'efectivo_contado.required' => 'Indica el efectivo contado.',
            'efectivo_contado.numeric' => 'El efectivo contado debe ser un número (0 o mayor).',
            'efectivo_contado.min' => 'El efectivo contado no puede ser negativo.',
            'efectivo_contado.max' => 'El efectivo contado no puede superar 999 999,99.',
            'qr_verificado.required' => 'Indica el monto QR verificado.',
            'qr_verificado.numeric' => 'El monto QR verificado debe ser un número (0 o mayor).',
            'qr_verificado.min' => 'El monto QR verificado no puede ser negativo.',
            'qr_verificado.max' => 'El monto QR verificado no puede superar 999 999,99.',
        ]);
        $caja = DB::transaction(function () use ($caja, $data): Caja {
            $caja = Caja::whereKey($caja->getKey())->lockForUpdate()->firstOrFail();
            if ($caja->estado !== 'ABIERTA') throw ValidationException::withMessages(['caja' => 'La caja ya está cerrada.']);
            $pagos = DB::table('venta_pago as vp')
                ->join('ventas as v', 'v.id_venta', '=', 'vp.id_venta')
                ->join('metodos_pago as mp', 'mp.id_metodo_pago', '=', 'vp.id_metodo_pago')
                ->where('v.id_caja', $caja->getKey())
                ->where('v.estado', 'CONFIRMADA')
                ->whereNull('vp.reemplazado_el')
                ->select('mp.nombre as metodo', 'vp.importe')
                ->get();
            $totales = CajaCalculadora::totalesPorMetodo($pagos->all());
            $efectivoEsperado = VentaCalculadora::aCentavos($caja->monto_inicial) + $totales['efectivo'];
            $qrEsperado = $totales['qr'];
            $diferenciaEfectivo = CajaCalculadora::diferencia($data['efectivo_contado'], $efectivoEsperado);
            $diferenciaQr = VentaCalculadora::aCentavos($data['qr_verificado']) - $qrEsperado;
            $caja->update([
                'fecha_cierre' => now(),
                'efectivo_contado' => $data['efectivo_contado'],
                'efectivo_esperado' => number_format($efectivoEsperado / 100, 2, '.', ''),
                'qr_verificado' => $data['qr_verificado'],
                'qr_esperado' => number_format($qrEsperado / 100, 2, '.', ''),
                'diferencia' => number_format($diferenciaEfectivo / 100, 2, '.', ''),
                'diferencia_qr' => number_format($diferenciaQr / 100, 2, '.', ''),
                'diferencia_total' => number_format(($diferenciaEfectivo + $diferenciaQr) / 100, 2, '.', ''),
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
