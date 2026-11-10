<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\MetodoPago;
use Illuminate\Http\Request;

class MetodoPagoController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_metodos_pago');
        $metodos = MetodoPago::query()
            ->when($request->filled('buscar'), fn ($q) => $q->where('nombre', 'ilike', '%'.$request->string('buscar')->toString().'%'))
            ->orderBy('nombre')->paginate(15)->withQueryString();
        $eliminados = MetodoPago::onlyTrashed()->orderBy('nombre')->get(['id_metodo_pago', 'nombre', 'eliminado_el']);

        return $request->expectsJson()
            ? response()->json($metodos)
            : view('metodos-pago.index', compact('metodos', 'eliminados'));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_metodos_pago');
        return view('metodos-pago.form', ['metodoPago' => new MetodoPago]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_metodos_pago');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:30', 'unique:metodos_pago,nombre'],
            'activo' => ['required', 'boolean'],
        ]);
        $data['creado_por'] = auth()->id();
        $metodoPago = MetodoPago::create($data);
        return $request->expectsJson()
            ? response()->json($metodoPago, 201)
            : redirect()->route('metodos-pago.index')->with('success', 'Método de pago registrado.');
    }

    public function show(MetodoPago $metodoPago)
    {
        $this->authorizePermission('gestionar_metodos_pago');
        return request()->expectsJson()
            ? response()->json($metodoPago->load('pagos'))
            : view('metodos-pago.form', compact('metodoPago'));
    }

    public function edit(MetodoPago $metodoPago)
    {
        return $this->show($metodoPago);
    }

    public function update(Request $request, MetodoPago $metodoPago)
    {
        $this->authorizePermission('gestionar_metodos_pago');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:30', 'unique:metodos_pago,nombre,'.$metodoPago->getKey().',id_metodo_pago'],
            'activo' => ['required', 'boolean'],
        ]);
        $data['modificado_por'] = auth()->id();
        $metodoPago->update($data);
        return $request->expectsJson()
            ? response()->json($metodoPago->fresh())
            : redirect()->route('metodos-pago.index')->with('success', 'Método de pago actualizado.');
    }

    public function destroy(MetodoPago $metodoPago)
    {
        $this->authorizePermission('gestionar_metodos_pago');
        abort_if($metodoPago->pagos()->exists(), 422, 'No se puede eliminar un método con historial de pagos.');
        $metodoPago->eliminado_por = auth()->id();
        $metodoPago->save();
        $metodoPago->delete();
        return $request->expectsJson()
            ? response()->json(['message' => 'Método de pago eliminado lógicamente.'])
            : redirect()->route('metodos-pago.index')->with('success', 'Método de pago eliminado lógicamente.');
    }

    public function restore(Request $request)
    {
        $this->authorizePermission('gestionar_metodos_pago');
        $data = $request->validate(['id_metodo_pago' => ['required', 'integer']]);
        $metodoPago = MetodoPago::onlyTrashed()->findOrFail($data['id_metodo_pago']);
        $metodoPago->restore();
        $metodoPago->update(['activo' => true, 'modificado_por' => auth()->id()]);

        return $request->expectsJson()
            ? response()->json($metodoPago->fresh())
            : redirect()->route('metodos-pago.index')->with('success', 'Método de pago reactivado.');
    }
}
