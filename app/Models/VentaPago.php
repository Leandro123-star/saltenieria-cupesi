<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaPago extends Model
{
    protected $table = 'venta_pago';

    protected $primaryKey = 'id_venta_pago';

    public $timestamps = false;

    protected $fillable = [
        'importe',
        'verificado',
        'id_venta',
        'id_metodo_pago',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
        'verificado' => 'boolean',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'id_venta', 'id_venta');
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'id_metodo_pago', 'id_metodo_pago');
    }
}
