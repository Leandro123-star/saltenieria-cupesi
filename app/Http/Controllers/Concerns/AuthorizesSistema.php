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

        return $usuario->roles()->where('nombre', 'Administrador')->exists()
            || $usuario->sucursales()->whereKey($idSucursal)->exists();
    }

    protected function authorizeSucursal(int $idSucursal): void
    {
        abort_unless($this->canUseSucursal($idSucursal), 403, 'No tiene acceso a esta sucursal.');
    }
}
