<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    protected $table = 'cajas';

    protected $primaryKey = 'id_caja';

    public const CREATED_AT = 'creado_el';

    public const UPDATED_AT = 'modificado_el';

    protected $fillable = [
        'fecha_apertura',
        'fecha_cierre',
        'monto_inicial',
        'efectivo_contado',
        'efectivo_esperado',
        'diferencia',
        'estado',
        'id_sucursal',
        'id_usuario_apertura',
        'id_usuario_cierre',
        'creado_por',
        'modificado_por',
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'monto_inicial' => 'decimal:2',
        'efectivo_contado' => 'decimal:2',
        'efectivo_esperado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'creado_el' => 'datetime',
        'modificado_el' => 'datetime',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id_sucursal');
    }

    public function usuarioApertura()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_apertura', 'id_usuario');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_cierre', 'id_usuario');
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'id_caja', 'id_caja');
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
