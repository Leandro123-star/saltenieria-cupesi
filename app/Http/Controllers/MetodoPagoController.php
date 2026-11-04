<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\MetodoPago;
use Illuminate\Http\Request;

class MetodoPagoController extends Controller
{
    use AuthorizesSistema;

    public function index()
    {
        $this->authorizePermission('gestionar_metodos_pago');
        return response()->json(MetodoPago::paginate(15));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_metodos_pago');
        return response()->json(['message' => 'Formulario pendiente.']);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_metodos_pago');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:30', 'unique:metodos_pago,nombre'],
            'activo' => ['required', 'boolean'],
        ]);
        $data['creado_por'] = auth()->id();
        return response()->json(MetodoPago::create($data), 201);
    }

    public function show(MetodoPago $metodoPago)
    {
        $this->authorizePermission('gestionar_metodos_pago');
        return response()->json($metodoPago->load('pagos'));
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
        return response()->json($metodoPago->fresh());
    }

    public function destroy(MetodoPago $metodoPago)
    {
        $this->authorizePermission('gestionar_metodos_pago');
        abort_if($metodoPago->pagos()->exists(), 422, 'No se puede eliminar un método con historial de pagos.');
        $metodoPago->eliminado_por = auth()->id();
        $metodoPago->save();
        $metodoPago->delete();
        return response()->json(['message' => 'Método de pago eliminado lógicamente.']);
    }
}
