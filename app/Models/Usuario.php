<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
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
        'creado_por',
        'modificado_por',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'password_hash' => 'hashed',
        'activo' => 'boolean',
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
