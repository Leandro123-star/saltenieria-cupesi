<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private const CREDENCIALES_INVALIDAS = 'Usuario o contraseña incorrectos.';

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // Sin reglas de longitud mínima: un error de formato revelaría la
        // política de contraseñas. Cualquier fallo usa el mensaje genérico.
        $credentials = $request->validate([
            'nombre_usuario' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:50'],
        ], [
            'nombre_usuario.required' => 'Ingresa tu nombre de usuario.',
            'password.required' => 'Ingresa tu contraseña.',
            'nombre_usuario.max' => self::CREDENCIALES_INVALIDAS,
            'password.max' => self::CREDENCIALES_INVALIDAS,
        ]);

        if (! Auth::attempt([
            'nombre_usuario' => $credentials['nombre_usuario'],
            'password' => $credentials['password'],
            'activo' => true,
        ])) {
            // Mismo mensaje para usuario inexistente, contraseña errónea o
            // cuenta inactiva: no se revela si el usuario existe.
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => self::CREDENCIALES_INVALIDAS,
                    'errors' => ['nombre_usuario' => [self::CREDENCIALES_INVALIDAS]],
                ], 422);
            }

            return redirect()->route('login')
                ->withErrors(['nombre_usuario' => self::CREDENCIALES_INVALIDAS])
                ->onlyInput('nombre_usuario');
        }

        $request->session()->regenerate();

        // Con acceso global incluye todas las sucursales activas (también las nuevas).
        $branches = Auth::user()->sucursalesAutorizadas()->get();
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
        // El contexto no debe sobrevivir al cierre de sesión, aunque el
        // controlador cambie posteriormente su estrategia de invalidación.
        $request->session()->forget('sucursal_activa_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson() || app()->runningUnitTests()
            ? response()->json(['message' => 'Sesión cerrada.'])
            : redirect()->route('login')->with('success', 'Sesión cerrada correctamente.');
    }

    public function chooseBranch()
    {
        abort_if(in_array(Auth::user()->rol?->nombre, ['Cajero', 'Cajero/Vendedor'], true), 403, 'Los Cajeros no pueden cambiar de sucursal.');
        $branches = Auth::user()->sucursalesAutorizadas()->orderBy('nombre')->get();
        return view('auth.branch', compact('branches'));
    }

    public function setBranch(Request $request)
    {
        abort_if(in_array(Auth::user()->rol?->nombre, ['Cajero', 'Cajero/Vendedor'], true), 403, 'Los Cajeros no pueden cambiar de sucursal.');
        $data = $request->validate(['id_sucursal' => ['required', 'integer', 'exists:sucursales,id_sucursal']]);
        // Solo sucursales activas y autorizadas (todas si tiene acceso global).
        abort_unless(Auth::user()->puedeUsarSucursal((int) $data['id_sucursal']), 403, 'No tiene acceso a esta sucursal.');
        $request->session()->put('sucursal_activa_id', (int) $data['id_sucursal']);
        return redirect()->intended(route('dashboard'))->with('success', 'Sucursal activa actualizada.');
    }
}
