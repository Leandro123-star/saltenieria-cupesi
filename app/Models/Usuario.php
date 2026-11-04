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
}
