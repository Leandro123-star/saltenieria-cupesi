<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'nombre_usuario' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'max:50'],
        ]);

        if (! Auth::attempt([
            'nombre_usuario' => $credentials['nombre_usuario'],
            'password' => $credentials['password'],
            'activo' => true,
        ])) {
            return response()->json(['message' => 'Credenciales inválidas.'], 422);
        }

        $request->session()->regenerate();

        $branches = Auth::user()->sucursales()->where('activo', true)
            ->where(function ($query): void {
                $query->whereNull('usuario_sucursal.fecha_fin')->orWhereDate('usuario_sucursal.fecha_fin', '>=', today());
            })->get();
        if ($branches->count() === 1) {
            $request->session()->put('sucursal_activa_id', $branches->first()->getKey());
        }
        if ($request->expectsJson()) return response()->json(['message' => 'Sesión iniciada.']);
        return redirect()->intended($branches->count() > 1 ? route('branch.choose') : route('dashboard'))
            ->with('success', 'Sesión iniciada correctamente.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson() || app()->runningUnitTests()
            ? response()->json(['message' => 'Sesión cerrada.'])
            : redirect()->route('login')->with('success', 'Sesión cerrada correctamente.');
    }

    public function chooseBranch()
    {
        abort_if(Auth::user()->roles()->where('nombre', 'Cajero')->orWhere('nombre', 'Cajero/Vendedor')->exists(), 403, 'Los Cajeros no pueden cambiar de sucursal.');
        $branches = Auth::user()->sucursales()->where('activo', true)
            ->where(function ($query): void {
                $query->whereNull('usuario_sucursal.fecha_fin')->orWhereDate('usuario_sucursal.fecha_fin', '>=', today());
            })->orderBy('nombre')->get();
        return view('auth.branch', compact('branches'));
    }

    public function setBranch(Request $request)
    {
        abort_if(Auth::user()->roles()->where('nombre', 'Cajero')->orWhere('nombre', 'Cajero/Vendedor')->exists(), 403, 'Los Cajeros no pueden cambiar de sucursal.');
        $data = $request->validate(['id_sucursal' => ['required', 'integer', 'exists:sucursales,id_sucursal']]);
        abort_unless(Auth::user()->sucursales()->whereKey($data['id_sucursal'])->where('activo', true)
            ->where(function ($query): void {
                $query->whereNull('usuario_sucursal.fecha_fin')->orWhereDate('usuario_sucursal.fecha_fin', '>=', today());
            })->exists(), 403);
        $request->session()->put('sucursal_activa_id', (int) $data['id_sucursal']);
        return redirect()->intended(route('dashboard'))->with('success', 'Sucursal activa actualizada.');
    }
}
