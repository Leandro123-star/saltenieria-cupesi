<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Sucursal;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    use AuthorizesSistema;

    public function index(Request $request)
    {
        $this->authorizePermission('gestionar_sucursales');
        $sucursales = Sucursal::withCount(['productos', 'ventas'])
            ->when($request->filled('buscar'), fn ($q) => $q->where('nombre', 'ilike', '%'.$request->string('buscar')->toString().'%'))
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
            'nombre' => ['required', 'string', 'max:50', 'unique:sucursales,nombre'],
            'direccion' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:15'],
            'activo' => ['required', 'boolean'],
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
            'nombre' => ['required', 'string', 'max:50', 'unique:sucursales,nombre,'.$sucursal->getKey().',id_sucursal'],
            'direccion' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:15'],
            'activo' => ['required', 'boolean'],
        ]);
        $data['modificado_por'] = auth()->id();
        $sucursal->update($data);
        return $request->expectsJson() ? response()->json($sucursal->fresh()) : redirect()->route('sucursales.index')->with('success', 'Sucursal actualizada.');
    }

    public function destroy(Sucursal $sucursal)
    {
        $this->authorizePermission('gestionar_sucursales');
        abort_if($sucursal->ventas()->exists(), 422, 'No se puede eliminar una sucursal con ventas.');
        $sucursal->eliminado_por = auth()->id();
        $sucursal->save();
        $sucursal->delete();
        return request()->expectsJson() ? response()->json(['message' => 'Sucursal eliminada lógicamente.']) : redirect()->route('sucursales.index')->with('success', 'Sucursal desactivada.');
    }
}
