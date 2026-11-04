<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permiso extends Model
{
    use SoftDeletes;

    protected $table = 'permisos';

    protected $primaryKey = 'id_permiso';

    public const CREATED_AT = 'creado_el';

    public const UPDATED_AT = 'modificado_el';

    public const DELETED_AT = 'eliminado_el';

    protected $fillable = [
        'nombre',
        'descripcion',
        'creado_por',
        'modificado_por',
        'eliminado_por',
    ];

    protected $casts = [
        'creado_el' => 'datetime',
        'modificado_el' => 'datetime',
        'eliminado_el' => 'datetime',
    ];

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso', 'id_permiso', 'id_rol');
    }

    public function creadoPor()
    {
        return $this->belongsTo(Usuario::class, 'creado_por', 'id_usuario');
    }

    public function modificadoPor()
    {
        return $this->belongsTo(Usuario::class, 'modificado_por', 'id_usuario');
    }

    public function eliminadoPor()
    {
        return $this->belongsTo(Usuario::class, 'eliminado_por', 'id_usuario');
    }
}
