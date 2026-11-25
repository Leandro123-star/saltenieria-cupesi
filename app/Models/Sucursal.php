<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $primaryKey = 'id_sucursal';

    public const CREATED_AT = 'creado_el';

    public const UPDATED_AT = 'modificado_el';

    protected $fillable = [
        'nombre',
        'direccion',
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

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'usuario_sucursal', 'id_sucursal', 'id_usuario')
            ->withPivot(['fecha_asignacion', 'fecha_fin']);
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'producto_sucursal', 'id_sucursal', 'id_producto')
            ->withPivot(['stock_actual', 'stock_minimo']);
    }

    public function cajas()
    {
        return $this->hasMany(Caja::class, 'id_sucursal', 'id_sucursal');
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'id_sucursal', 'id_sucursal');
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
