<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSucursalContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario || $usuario->roles()->where('nombre', 'Administrador')->exists()) {
            return $next($request);
        }

        $sucursales = $usuario->sucursales()
            ->where('sucursales.activo', true)
            ->where(function ($query): void {
                $query->whereNull('usuario_sucursal.fecha_fin')
                    ->orWhereDate('usuario_sucursal.fecha_fin', '>=', today());
            })
            ->get();

        if ($sucursales->count() !== 1) {
            $request->session()->forget('sucursal_activa_id');
            abort(403, 'Su usuario no tiene una sucursal activa asignada. Solicite al administrador una asignación válida.');
        }

        // Recalcula el contexto en cada solicitud: un cambio administrativo
        // no puede dejar al Cajero operando con la sucursal anterior.
        if ((int) $request->session()->get('sucursal_activa_id') !== (int) $sucursales->first()->getKey()) {
            $request->session()->put('sucursal_activa_id', $sucursales->first()->getKey());
        }

        return $next($request);
    }
}
