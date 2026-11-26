<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $primaryKey = 'id_venta';

    public const CREATED_AT = 'creado_el';

    public const UPDATED_AT = 'modificado_el';

    protected $fillable = [
        'fecha_venta',
        'total',
        'estado',
        'id_sucursal',
        'id_usuario',
        'id_caja',
        'creado_por',
        'modificado_por',
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'total' => 'decimal:2',
        'creado_el' => 'datetime',
        'modificado_el' => 'datetime',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id_sucursal');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'id_caja', 'id_caja');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta')
            ->whereNull('reemplazado_el');
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'detalle_venta', 'id_venta', 'id_producto')
            ->withPivot(['cantidad', 'precio_unitario', 'subtotal'])
            ->wherePivotNull('reemplazado_el');
    }

    public function pagos()
    {
        return $this->hasMany(VentaPago::class, 'id_venta', 'id_venta')
            ->whereNull('reemplazado_el');
    }

    public function metodosPago()
    {
        return $this->belongsToMany(MetodoPago::class, 'venta_pago', 'id_venta', 'id_metodo_pago')
            ->withPivot(['importe', 'verificado'])
            ->wherePivotNull('reemplazado_el');
    }

    public function correcciones()
    {
        return $this->hasMany(VentaCorreccion::class, 'id_venta', 'id_venta')
            ->latest('creado_el');
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
