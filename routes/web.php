<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->view('auth.login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/sucursal-activa', [AuthController::class, 'chooseBranch'])->name('branch.choose');
    Route::post('/sucursal-activa', [AuthController::class, 'setBranch'])->name('branch.set');

    Route::resource('personal', PersonalController::class);
    Route::resource('usuarios', UsuarioController::class);
    Route::post('usuarios/{usuario}/reset-password', [UsuarioController::class, 'resetPassword'])->name('usuarios.reset-password');
    Route::resource('roles', RolController::class)->parameters(['roles' => 'rol']);
    Route::post('roles/{rol}/permisos', [RolController::class, 'syncPermisos'])->name('roles.permisos');
    Route::resource('permisos', PermisoController::class)->only(['index', 'store', 'update'])->parameters(['permisos' => 'permiso']);
    Route::resource('sucursales', SucursalController::class)->parameters(['sucursales' => 'sucursal']);
    Route::resource('categorias', CategoriaController::class);
    Route::post('categorias/reactivar', [CategoriaController::class, 'restore'])->name('categorias.restore');
    Route::resource('productos', ProductoController::class);
    Route::patch('productos/{producto}/stock', [ProductoController::class, 'updateStock'])->name('productos.stock');
    Route::resource('metodos-pago', MetodoPagoController::class)->parameters(['metodos-pago' => 'metodoPago']);

    Route::get('inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('inventario/producto/{producto}', [InventarioController::class, 'showProducto'])->name('inventario.producto');
    Route::get('inventario/stock-bajo', [InventarioController::class, 'stockBajo'])->name('inventario.stock-bajo');
    Route::patch('inventario/producto/{producto}/stock-minimo', [InventarioController::class, 'updateStockMinimo'])->name('inventario.stock-minimo');

    Route::get('ventas', [VentaController::class, 'index'])->name('ventas.index');
    Route::get('ventas/create', [VentaController::class, 'create'])->name('ventas.create');
    Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
    Route::get('ventas/{venta}/comprobante', [VentaController::class, 'receipt'])->name('ventas.receipt');

    Route::get('cajas', [CajaController::class, 'index'])->name('cajas.index');
    Route::post('cajas/abrir', [CajaController::class, 'abrir'])->name('cajas.abrir');
    Route::get('cajas/estado/actual', [CajaController::class, 'estado'])->name('cajas.estado');
    Route::post('cajas/{caja}/cerrar', [CajaController::class, 'cerrar'])->name('cajas.cerrar');
    Route::get('cajas/{caja}', [CajaController::class, 'show'])->name('cajas.show');

    Route::get('reportes/ventas-periodo', [ReporteController::class, 'ventasPorPeriodo'])->name('reportes.ventas-periodo');
    Route::get('reportes/ventas-sucursal', [ReporteController::class, 'ventasPorSucursal'])->name('reportes.ventas-sucursal');
    Route::get('reportes/productos-mas-vendidos', [ReporteController::class, 'productosMasVendidos'])->name('reportes.productos-mas-vendidos');
    Route::get('reportes/existencias', [ReporteController::class, 'existencias'])->name('reportes.existencias');
    Route::get('reportes/pagos', [ReporteController::class, 'pagos'])->name('reportes.pagos');
    Route::get('reportes/cajas', [ReporteController::class, 'cajas'])->name('reportes.cajas');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
