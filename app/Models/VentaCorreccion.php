<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaCorreccion extends Model
{
    protected $table = 'venta_correcciones';

    protected $primaryKey = 'id_correccion';

    public const CREATED_AT = 'creado_el';

    public const UPDATED_AT = null;

    protected $fillable = [
        'motivo',
        'total_anterior',
        'total_posterior',
        'datos_anteriores',
        'datos_posteriores',
        'id_venta',
        'id_usuario',
        'rol',
        'id_sucursal',
        'id_caja',
    ];

    protected $casts = [
        'total_anterior' => 'decimal:2',
        'total_posterior' => 'decimal:2',
        'datos_anteriores' => 'array',
        'datos_posteriores' => 'array',
        'creado_el' => 'datetime',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'id_venta', 'id_venta');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id_sucursal');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'id_caja', 'id_caja');
    }
}
