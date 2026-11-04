<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Sucursal;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    use AuthorizesSistema;

    public function index()
    {
        $this->authorizePermission('gestionar_sucursales');
        return response()->json(Sucursal::withCount(['productos', 'ventas'])->paginate(15));
    }

    public function create()
    {
        $this->authorizePermission('gestionar_sucursales');
        return response()->json(['message' => 'Formulario de sucursal pendiente.']);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('gestionar_sucursales');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'unique:sucursales,nombre'],
            'direccion' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:15'],
            'activo' => ['required', 'boolean'],
        ]);
        $data['creado_por'] = auth()->id();
        return response()->json(Sucursal::create($data), 201);
    }

    public function show(Sucursal $sucursal)
    {
        $this->authorizePermission('gestionar_sucursales');
        return response()->json($sucursal->load(['usuarios', 'productos']));
    }

    public function edit(Sucursal $sucursal)
    {
        return $this->show($sucursal);
    }

    public function update(Request $request, Sucursal $sucursal)
    {
        $this->authorizePermission('gestionar_sucursales');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'unique:sucursales,nombre,'.$sucursal->getKey().',id_sucursal'],
            'direccion' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:15'],
            'activo' => ['required', 'boolean'],
        ]);
        $data['modificado_por'] = auth()->id();
        $sucursal->update($data);
        return response()->json($sucursal->fresh());
    }

    public function destroy(Sucursal $sucursal)
    {
        $this->authorizePermission('gestionar_sucursales');
        abort_if($sucursal->ventas()->exists(), 422, 'No se puede eliminar una sucursal con ventas.');
        $sucursal->eliminado_por = auth()->id();
        $sucursal->save();
        $sucursal->delete();
        return response()->json(['message' => 'Sucursal eliminada lógicamente.']);
    }
}
