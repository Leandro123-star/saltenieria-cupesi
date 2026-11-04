<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public const CREATED_AT = 'creado_el';

    public const UPDATED_AT = 'modificado_el';

    protected $fillable = [
        'nombre_usuario',
        'password_hash',
        'activo',
        'id_personal',
        'creado_por',
        'modificado_por',
        'eliminado_por',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'password_hash' => 'hashed',
        'activo' => 'boolean',
        'creado_el' => 'datetime',
        'modificado_el' => 'datetime',
        'eliminado_el' => 'datetime',
    ];

    public const DELETED_AT = 'eliminado_el';

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'id_personal', 'id_personal');
    }

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol', 'id_usuario', 'id_rol');
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

    public function eliminadoPor()
    {
        return $this->belongsTo(self::class, 'eliminado_por', 'id_usuario');
    }
}
