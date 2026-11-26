<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permisos';

    protected $primaryKey = 'id_permiso';

    public const CREATED_AT = 'creado_el';

    public const UPDATED_AT = 'modificado_el';

    protected $fillable = [
        'nombre',
        'descripcion',
        'creado_por',
        'modificado_por',
    ];

    protected $casts = [
        'creado_el' => 'datetime',
        'modificado_el' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Permiso $permiso): void {
            if (blank($permiso->clave)) {
                $permiso->clave = static::claveDesdeNombre((string) $permiso->nombre);
            }
        });
    }

    /**
     * Deriva la clave interna estable a partir del nombre técnico visible.
     * Los espacios se convierten en guiones bajos para que las comprobaciones
     * de autorización sigan usando identificadores como "visualizar_panel".
     */
    public static function claveDesdeNombre(string $nombre): string
    {
        return (string) preg_replace('/\s+/u', '_', trim($nombre));
    }

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
}
