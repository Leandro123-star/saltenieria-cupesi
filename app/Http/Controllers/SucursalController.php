<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Sucursal;
use App\Support\Formatos;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_sucursales');
        $sucursales = Sucursal::withCount(['productos', 'ventas'])
            ->orderBy('nombre')->paginate(15)->withQueryString();
        return $request->expectsJson() ? response()->json($sucursales) : view('sucursales.index', compact('sucursales'));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_sucursales');
        return view('sucursales.form', ['sucursal' => new Sucursal]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_sucursales');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'regex:'.Formatos::NOMBRE_COMERCIAL, 'unique:sucursales,nombre'],
            'direccion' => ['required', 'string', 'max:150', 'regex:'.Formatos::DIRECCION],
            'telefono' => ['nullable', 'string', 'max:15', 'regex:'.Formatos::TELEFONO],
            'activo' => ['required', 'boolean'],
        ], [
            'nombre.regex' => 'El nombre de la sucursal debe contener letras y no puede incluir símbolos como @, #, $, < o >.',
            'nombre.unique' => 'Ya existe una sucursal con ese nombre.',
            'direccion.regex' => 'La dirección solo puede contener letras, números, espacios y los signos . , # ° / - ( ).',
            'telefono.regex' => 'El teléfono tiene un formato incorrecto: usa de 7 a 14 dígitos, con + opcional al inicio.',
        ]);
        $data['creado_por'] = auth()->id();
        $sucursal = Sucursal::create($data);
        return $request->expectsJson() ? response()->json($sucursal, 201) : redirect()->route('sucursales.index')->with('success', 'Sucursal registrada.');
    }

    public function show(Sucursal $sucursal)
    {
        $this->authorizePermission('gestionar_sucursales');
        return request()->expectsJson() ? response()->json($sucursal->load(['usuarios', 'productos'])) : view('sucursales.form', compact('sucursal'));
    }

    public function edit(Sucursal $sucursal)
    {
        return $this->show($sucursal);
    }

    public function update(Request $request, Sucursal $sucursal)
    {
        $this->authorizePermission('gestionar_sucursales');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'regex:'.Formatos::NOMBRE_COMERCIAL, 'unique:sucursales,nombre,'.$sucursal->getKey().',id_sucursal'],
            'direccion' => ['required', 'string', 'max:150', 'regex:'.Formatos::DIRECCION],
            'telefono' => ['nullable', 'string', 'max:15', 'regex:'.Formatos::TELEFONO],
            'activo' => ['required', 'boolean'],
        ], [
            'nombre.regex' => 'El nombre de la sucursal debe contener letras y no puede incluir símbolos como @, #, $, < o >.',
            'nombre.unique' => 'Ya existe una sucursal con ese nombre.',
            'direccion.regex' => 'La dirección solo puede contener letras, números, espacios y los signos . , # ° / - ( ).',
            'telefono.regex' => 'El teléfono tiene un formato incorrecto: usa de 7 a 14 dígitos, con + opcional al inicio.',
        ]);
        $data['modificado_por'] = auth()->id();
        $sucursal->update($data);
        return $request->expectsJson() ? response()->json($sucursal->fresh()) : redirect()->route('sucursales.index')->with('success', 'Sucursal actualizada.');
    }

    public function destroy(Sucursal $sucursal)
    {
        $this->authorizePermission('gestionar_sucursales');
        if ($sucursal->ventas()->exists()) {
            $mensaje = 'No se puede desactivar una sucursal con ventas.';
            abort_if(request()->expectsJson(), 422, $mensaje);

            return redirect()->route('sucursales.index')->with('error', $mensaje);
        }
        $sucursal->update(['activo' => false, 'modificado_por' => auth()->id()]);
        return request()->expectsJson() ? response()->json(['message' => 'Sucursal desactivada.']) : redirect()->route('sucursales.index')->with('success', 'Sucursal desactivada.');
    }
}
