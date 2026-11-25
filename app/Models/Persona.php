<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    protected $table = 'personas';

    protected $primaryKey = 'id_persona';

    public const CREATED_AT = 'creado_el';

    public const UPDATED_AT = 'modificado_el';

    protected $fillable = [
        'nombres',
        'apellidos',
        'ci',
        'telefono',
        'activo',
        'creado_por',
        'modificado_por',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'creado_el' => 'datetime',
        'modificado_el' => 'datetime',
    ];

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'id_persona', 'id_persona');
    }

    public function creadoPor()
    {
        return $this->belongsTo(Usuario::class, 'creado_por', 'id_usuario');
    }

    public function modificadoPor()
    {
        return $this->belongsTo(Usuario::class, 'modificado_por', 'id_usuario');
    }
}
