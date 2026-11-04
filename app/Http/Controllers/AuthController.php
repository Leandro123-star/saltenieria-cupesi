<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return response()->json(['message' => 'Vista de inicio de sesión pendiente.']);
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

        return response()->json(['message' => 'Sesión iniciada.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
