<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\MetodoPago;
use App\Support\Formatos;
use Illuminate\Http\Request;

class MetodoPagoController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_metodos_pago');
        $metodos = MetodoPago::query()->orderBy('nombre')->paginate(15)->withQueryString();

        return $request->expectsJson()
            ? response()->json($metodos)
            : view('metodos-pago.index', compact('metodos'));
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
            'nombre' => ['required', 'string', 'max:30', 'regex:'.Formatos::NOMBRE_COMERCIAL, 'unique:metodos_pago,nombre'],
            'activo' => ['required', 'boolean'],
        ], [
            'nombre.regex' => 'El nombre del método de pago debe contener letras y no puede incluir símbolos como @, #, $, < o >.',
            'nombre.unique' => 'Ya existe un método de pago con ese nombre.',
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
            'nombre' => ['required', 'string', 'max:30', 'regex:'.Formatos::NOMBRE_COMERCIAL, 'unique:metodos_pago,nombre,'.$metodoPago->getKey().',id_metodo_pago'],
            'activo' => ['required', 'boolean'],
        ], [
            'nombre.regex' => 'El nombre del método de pago debe contener letras y no puede incluir símbolos como @, #, $, < o >.',
            'nombre.unique' => 'Ya existe un método de pago con ese nombre.',
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
        if (! $metodoPago->activo) {
            return $this->respuestaEstado('error', 'El método de pago ya está inactivo.');
        }
        // Solo cambia el estado: los pagos históricos conservan su método e importe.
        $metodoPago->update(['activo' => false, 'modificado_por' => auth()->id()]);

        return $this->respuestaEstado('success', 'Método de pago desactivado correctamente.');
    }

    public function restore(MetodoPago $metodoPago)
    {
        $this->authorizePermission('gestionar_metodos_pago');
        if ($metodoPago->activo) {
            return $this->respuestaEstado('error', 'El método de pago ya está activo.');
        }
        $metodoPago->update(['activo' => true, 'modificado_por' => auth()->id()]);

        return $this->respuestaEstado('success', 'Método de pago activado correctamente.');
    }

    private function respuestaEstado(string $tipo, string $mensaje)
    {
        return request()->expectsJson()
            ? response()->json(['message' => $mensaje])
            : redirect()->route('metodos-pago.index')->with($tipo, $mensaje);
    }
}
