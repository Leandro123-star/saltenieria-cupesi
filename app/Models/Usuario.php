<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public const CREATED_AT = 'creado_el';

    public const UPDATED_AT = 'modificado_el';

    protected $fillable = [
        'nombre_usuario',
        'password_hash',
        'activo',
        'id_persona',
        'id_rol',
        'acceso_todas_sucursales',
        'creado_por',
        'modificado_por',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'password_hash' => 'hashed',
        'activo' => 'boolean',
        'acceso_todas_sucursales' => 'boolean',
        'creado_el' => 'datetime',
        'modificado_el' => 'datetime',
    ];

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function esAdministrador(): bool
    {
        return $this->rol?->nombre === 'Administrador';
    }

    public function tienePermiso(string $permiso): bool
    {
        return $this->rol?->activo
            && $this->rol->permisos()->where('permisos.clave', $permiso)->exists();
    }

    public function sucursales()
    {
        return $this->belongsToMany(Sucursal::class, 'usuario_sucursal', 'id_usuario', 'id_sucursal')
            ->withPivot(['fecha_asignacion', 'fecha_fin']);
    }

    /**
     * Acceso a todas las sucursales activas, incluidas las que se creen
     * después. Solo aplica al rol Administrador: para cualquier otro rol la
     * marca se ignora.
     */
    public function tieneAccesoGlobal(): bool
    {
        return $this->esAdministrador() && (bool) $this->acceso_todas_sucursales;
    }

    /** Sucursales activas que el usuario puede usar. */
    public function sucursalesAutorizadas(): Builder
    {
        $query = Sucursal::query()->where('sucursales.activo', true);

        if ($this->tieneAccesoGlobal()) {
            return $query;
        }

        return $query->whereIn('sucursales.id_sucursal', DB::table('usuario_sucursal')
            ->select('id_sucursal')
            ->where('id_usuario', $this->getKey())
            ->where(function ($q): void {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', today());
            }));
    }

    /**
     * Indica si el usuario puede operar en la sucursal. Con $soloActivas en
     * false, el acceso global también permite consultar el historial de
     * sucursales desactivadas.
     */
    public function puedeUsarSucursal(int $idSucursal, bool $soloActivas = true): bool
    {
        if (! $soloActivas && $this->tieneAccesoGlobal()) {
            return Sucursal::whereKey($idSucursal)->exists();
        }

        return $this->sucursalesAutorizadas()->whereKey($idSucursal)->exists();
    }

    /** Sucursal activa de la sesión, solo si el usuario sigue autorizado. */
    public function sucursalActiva(): ?Sucursal
    {
        $id = session('sucursal_activa_id');

        return $id ? $this->sucursalesAutorizadas()->whereKey($id)->first() : null;
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'id_usuario', 'id_usuario');
    }

    public function cajasAperturadas()
    {
        return $this->hasMany(Caja::class, 'id_usuario_apertura', 'id_usuario');
    }

    public function cajasCerradas()
    {
        return $this->hasMany(Caja::class, 'id_usuario_cierre', 'id_usuario');
    }

    public function creadoPor()
    {
        return $this->belongsTo(self::class, 'creado_por', 'id_usuario');
    }

    public function modificadoPor()
    {
        return $this->belongsTo(self::class, 'modificado_por', 'id_usuario');
    }
}
