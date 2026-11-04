<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rol extends Model
{
    use SoftDeletes;

    protected $table = 'roles';

    protected $primaryKey = 'id_rol';

    public const CREATED_AT = 'creado_el';

    public const UPDATED_AT = 'modificado_el';

    public const DELETED_AT = 'eliminado_el';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
        'creado_por',
        'modificado_por',
        'eliminado_por',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'creado_el' => 'datetime',
        'modificado_el' => 'datetime',
        'eliminado_el' => 'datetime',
    ];

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'usuario_rol', 'id_rol', 'id_usuario');
    }

    public function permisos()
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'id_rol', 'id_permiso');
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
