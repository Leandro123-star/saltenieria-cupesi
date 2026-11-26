<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    protected $table = 'metodos_pago';

    protected $primaryKey = 'id_metodo_pago';

    public const CREATED_AT = 'creado_el';

    public const UPDATED_AT = 'modificado_el';

    protected $fillable = [
        'nombre',
        'activo',
        'creado_por',
        'modificado_por',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'creado_el' => 'datetime',
        'modificado_el' => 'datetime',
    ];

    public function pagos()
    {
        return $this->hasMany(VentaPago::class, 'id_metodo_pago', 'id_metodo_pago');
    }

    public function ventas()
    {
        return $this->belongsToMany(Venta::class, 'venta_pago', 'id_metodo_pago', 'id_venta')
            ->withPivot(['importe', 'verificado'])
            ->wherePivotNull('reemplazado_el');
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
