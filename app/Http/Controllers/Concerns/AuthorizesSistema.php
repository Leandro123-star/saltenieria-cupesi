<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

trait AuthorizesSistema
{
    protected function authorizePermission(string $permission): void
    {
        abort_unless(Auth::check(), 401);

        $usuario = Auth::user();
        $rol = $usuario->rol;

        $allowed = $rol && $rol->activo && (
            $rol->nombre === 'Administrador'
            || $rol->permisos()->where('permisos.clave', $permission)->exists()
        );

        abort_unless($allowed, 403, 'No tiene permiso para realizar esta operación.');
    }

    protected function authorizeAnyPermission(array $permissions): void
    {
        abort_unless(Auth::check(), 401);

        $usuario = Auth::user();
        $rol = $usuario->rol;

        $allowed = $rol && $rol->activo && (
            $rol->nombre === 'Administrador'
            || $rol->permisos()->whereIn('permisos.clave', $permissions)->exists()
        );

        abort_unless($allowed, 403, 'No tiene permiso para realizar esta operación.');
    }

    protected function canUseSucursal(int $idSucursal): bool
    {
        // El acceso global es explícito (usuarios.acceso_todas_sucursales).
        // Un Administrador sin sucursales y sin esa marca no tiene acceso.
        // Con acceso global también se consulta el historial de sucursales
        // desactivadas; el acceso específico exige sucursal activa y vigente.
        return Auth::user()->puedeUsarSucursal($idSucursal, soloActivas: false);
    }

    protected function activeSucursalIds()
    {
        return Auth::user()->sucursalesAutorizadas()->pluck('sucursales.id_sucursal');
    }

    /** true si los listados deben limitarse a activeSucursalIds(). */
    protected function limitarASucursalesAutorizadas(): bool
    {
        return ! Auth::user()->tieneAccesoGlobal();
    }

    protected function authorizeSucursal(int $idSucursal): void
    {
        abort_unless($this->canUseSucursal($idSucursal), 403, 'No tiene acceso a esta sucursal.');
    }
}
