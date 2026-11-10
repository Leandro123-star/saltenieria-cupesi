<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

trait AuthorizesSistema
{
    protected function authorizePermission(string $permission): void
    {
        abort_unless(Auth::check(), 401);

        $usuario = Auth::user();

        $allowed = $usuario->roles()
            ->where('nombre', 'Administrador')
            ->orWhereHas('permisos', fn ($query) => $query->where('nombre', $permission))
            ->exists();

        abort_unless($allowed, 403, 'No tiene permiso para realizar esta operación.');
    }

    protected function canUseSucursal(int $idSucursal): bool
    {
        $usuario = Auth::user();

        // Mantiene compatibilidad con instalaciones antiguas donde el
        // Administrador aún no tenía filas en usuario_sucursal. En cuanto
        // tenga autorizaciones explícitas, solo puede usar esas sucursales.
        if ($usuario->roles()->where('nombre', 'Administrador')->exists()
            && ! $usuario->sucursales()->exists()) {
            return true;
        }

        return $usuario->sucursales()
            ->whereKey($idSucursal)
            ->where('sucursales.activo', true)
            ->where(function ($query): void {
                $query->whereNull('usuario_sucursal.fecha_fin')
                    ->orWhereDate('usuario_sucursal.fecha_fin', '>=', today());
            })->exists();
    }

    protected function activeSucursalIds()
    {
        return Auth::user()->sucursales()
            ->where('sucursales.activo', true)
            ->where(function ($query): void {
                $query->whereNull('usuario_sucursal.fecha_fin')
                    ->orWhereDate('usuario_sucursal.fecha_fin', '>=', today());
            })->pluck('sucursales.id_sucursal');
    }

    protected function authorizeSucursal(int $idSucursal): void
    {
        abort_unless($this->canUseSucursal($idSucursal), 403, 'No tiene acceso a esta sucursal.');
    }
}
