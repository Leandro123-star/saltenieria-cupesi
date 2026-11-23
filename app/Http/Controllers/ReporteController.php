<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSistema;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\MetodoPago;
use App\Models\Sucursal;
use App\Support\CajaCalculadora;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    use AuthorizesSistema;

    private const MONEY_KEYS = [
        'total', 'monto_inicial', 'total_ventas', 'efectivo_esperado', 'efectivo_contado',
        'diferencia_efectivo', 'qr_esperado', 'qr_verificado', 'diferencia_qr', 'diferencia_total',
    ];

    public function ventasPorPeriodo(Request $request)
    {
        $data = $this->ventasPeriodoData($request);
        return $request->expectsJson() ? response()->json($data['json']) : view('reportes.ventas-periodo', $data);
    }

    public function ventasPorPeriodoPdf(Request $request)
    {
        return $this->pdf($this->ventasPeriodoData($request), 'Ventas por período', 'ventas-por-periodo');
    }

    public function ventasPorSucursal(Request $request)
    {
        $data = $this->ventasSucursalData($request);
        return $request->expectsJson() ? response()->json($data['json']) : view('reportes.ventas-sucursal', $data);
    }

    public function ventasPorSucursalPdf(Request $request)
    {
        return $this->pdf($this->ventasSucursalData($request), 'Ventas por sucursal', 'ventas-por-sucursal');
    }

    public function productosMasVendidos(Request $request)
    {
        $data = $this->productosData($request);
        return $request->expectsJson() ? response()->json($data['json']) : view('reportes.productos-mas-vendidos', $data);
    }

    public function productosMasVendidosPdf(Request $request)
    {
        return $this->pdf($this->productosData($request), 'Productos más vendidos', 'productos-mas-vendidos');
    }

    public function existencias(Request $request)
    {
        $data = $this->existenciasData($request);
        return $request->expectsJson() ? response()->json($data['json']) : view('reportes.existencias', $data);
    }

    public function existenciasPdf(Request $request)
    {
        return $this->pdf($this->existenciasData($request), 'Existencias por sucursal', 'existencias-por-sucursal');
    }

    public function pagos(Request $request)
    {
        $data = $this->pagosData($request);
        return $request->expectsJson() ? response()->json($data['json']) : view('reportes.pagos', $data);
    }

    public function pagosPdf(Request $request)
    {
        return $this->pdf($this->pagosData($request), 'Totales por método de pago', 'totales-metodo-pago');
    }

    public function cajas(Request $request)
    {
        $data = $this->cajasData($request);
        return $request->expectsJson() ? response()->json($data['json']) : view('reportes.cajas', $data);
    }

    public function cajasPdf(Request $request)
    {
        return $this->pdf($this->cajasData($request), 'Historial de caja', 'historial-caja');
    }

    private function validatePeriod(Request $request): array
    {
        return $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'id_sucursal' => ['nullable', 'integer'],
        ]);
    }

    private function salesBase(Request $request, array $data = [])
    {
        $query = DB::table('ventas as v')
            ->when(! empty($data['desde']), fn ($q) => $q->whereDate('v.fecha_venta', '>=', $data['desde']))
            ->when(! empty($data['hasta']), fn ($q) => $q->whereDate('v.fecha_venta', '<=', $data['hasta']))
            ->when(! empty($data['id_sucursal']), fn ($q) => $q->where('v.id_sucursal', $data['id_sucursal']));

        return $this->scopeBranches($query, 'v.id_sucursal', $data['id_sucursal'] ?? null);
    }

    private function scopeBranches($query, string $column, ?int $selected = null)
    {
        if ($selected) {
            $this->authorizeSucursal($selected);
        }

        $allowed = $this->activeSucursalIds();
        $admin = auth()->user()->roles()->where('nombre', 'Administrador')->exists();
        if (! $admin || $allowed->isNotEmpty()) {
            $query->whereIn($column, $allowed);
        }

        return $query;
    }

    private function reportBranches()
    {
        $allowed = $this->activeSucursalIds();
        $admin = auth()->user()->roles()->where('nombre', 'Administrador')->exists();

        return Sucursal::query()->where('activo', true)
            ->when(! $admin || $allowed->isNotEmpty(), fn ($q) => $q->whereIn('id_sucursal', $allowed))
            ->orderBy('nombre')->get();
    }

    private function ventasPeriodoData(Request $request): array
    {
        $this->authorizePermission('generar_reportes');
        $filters = $this->validatePeriod($request);
        $base = $this->salesBase($request, $filters);
        $rows = (clone $base)->join('sucursales as s', 's.id_sucursal', '=', 'v.id_sucursal')
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'v.id_usuario')
            ->leftJoin('personal as per', 'per.id_personal', '=', 'u.id_personal')
            ->select('v.id_venta', 'v.fecha_venta', 'v.total', 'v.estado', 's.nombre as sucursal')
            ->selectRaw("COALESCE(NULLIF(CONCAT_WS(' ', per.nombres, per.apellidos), ''), u.nombre_usuario, '—') as cajero")
            ->orderByDesc('v.fecha_venta')->get();

        $stats = [
            'ventas' => (clone $base)->count('v.id_venta'),
            'total' => (float) ((clone $base)->sum('v.total') ?? 0),
            'promedio' => (float) ((clone $base)->avg('v.total') ?? 0),
        ];
        $json = (clone $base)->selectRaw('DATE(v.fecha_venta) fecha, COUNT(*) cantidad, SUM(v.total) total')
            ->groupByRaw('DATE(v.fecha_venta)')->orderBy('fecha')->get();

        return $this->payload('Ventas por período', 'Consulta las ventas realizadas dentro del período seleccionado.', $filters, $rows, $stats, $json, $this->reportBranches(), [
            ['label' => 'Número de venta', 'key' => 'id_venta'], ['label' => 'Fecha y hora', 'key' => 'fecha_venta'], ['label' => 'Sucursal', 'key' => 'sucursal'], ['label' => 'Cajero', 'key' => 'cajero'], ['label' => 'Total', 'key' => 'total'], ['label' => 'Estado', 'key' => 'estado'],
        ]);
    }

    private function ventasSucursalData(Request $request): array
    {
        $this->authorizePermission('generar_reportes');
        $filters = $this->validatePeriod($request);
        $base = $this->salesBase($request, $filters);
        $rows = (clone $base)->join('sucursales as s', 's.id_sucursal', '=', 'v.id_sucursal')
            ->select('s.id_sucursal', 's.nombre as sucursal')->selectRaw('COUNT(*) cantidad, COALESCE(SUM(v.total), 0) total')
            ->groupBy('s.id_sucursal', 's.nombre')->orderByDesc('total')->get();
        $grandTotal = (float) $rows->sum('total');
        $rows->transform(function ($row) use ($grandTotal) {
            $row->participacion = $grandTotal > 0 ? ((float) $row->total / $grandTotal) * 100 : 0;
            return $row;
        });

        return $this->payload('Ventas por sucursal', 'Compara el rendimiento de las sucursales autorizadas.', $filters, $rows, ['ventas' => $rows->sum('cantidad'), 'total' => $grandTotal], $rows->map(fn ($r) => (object) ['nombre' => $r->sucursal, 'cantidad' => $r->cantidad, 'total' => $r->total]), $this->reportBranches(), [
            ['label' => 'Sucursal', 'key' => 'sucursal'], ['label' => 'Cantidad de ventas', 'key' => 'cantidad'], ['label' => 'Total vendido', 'key' => 'total'], ['label' => 'Participación', 'key' => 'participacion'],
        ]);
    }

    private function productosData(Request $request): array
    {
        $this->authorizePermission('generar_reportes');
        $filters = array_merge($this->validatePeriod($request), $request->validate(['limite' => ['nullable', 'integer', 'in:5,10,20']]));
        $filters['limite'] = (int) ($filters['limite'] ?? 10);
        $base = $this->salesBase($request, $filters);
        $rows = (clone $base)->join('detalle_venta as dv', 'dv.id_venta', '=', 'v.id_venta')
            ->join('productos as p', 'p.id_producto', '=', 'dv.id_producto')
            ->leftJoin('categorias as c', 'c.id_categoria', '=', 'p.id_categoria')
            ->select('p.id_producto', 'p.nombre', 'c.nombre as categoria')
            ->selectRaw('SUM(dv.cantidad) cantidad, COALESCE(SUM(dv.subtotal), 0) total')
            ->groupBy('p.id_producto', 'p.nombre', 'c.nombre')->orderByDesc('cantidad')->limit($filters['limite'])->get();
        $rows->each(fn ($row, $index) => $row->posicion = $index + 1);

        return $this->payload('Productos más vendidos', 'Ranking de productos según las unidades vendidas.', $filters, $rows, ['unidades' => (int) $rows->sum('cantidad'), 'diferentes' => $rows->count()], $rows->map(fn ($r) => (object) ['id_producto' => $r->id_producto, 'nombre' => $r->nombre, 'cantidad' => $r->cantidad]), $this->reportBranches(), [
            ['label' => '#', 'key' => 'posicion'], ['label' => 'Producto', 'key' => 'nombre'], ['label' => 'Categoría', 'key' => 'categoria'], ['label' => 'Cantidad', 'key' => 'cantidad'], ['label' => 'Total generado', 'key' => 'total'],
        ]);
    }

    private function existenciasData(Request $request): array
    {
        $this->authorizePermission('consultar_inventario');
        $filters = $request->validate(['id_sucursal' => ['nullable', 'integer'], 'id_categoria' => ['nullable', 'integer'], 'estado_stock' => ['nullable', 'in:AGOTADO,BAJO,NORMAL']]);
        $idSucursal = ! empty($filters['id_sucursal']) ? (int) $filters['id_sucursal'] : null;
        $query = DB::table('producto_sucursal as ps')->join('productos as p', 'p.id_producto', '=', 'ps.id_producto')
            ->leftJoin('categorias as c', 'c.id_categoria', '=', 'p.id_categoria')->join('sucursales as s', 's.id_sucursal', '=', 'ps.id_sucursal')
            ->select('p.id_producto', 'p.nombre as producto', 'c.nombre as categoria', 's.nombre as sucursal', 'ps.stock_actual', 'ps.stock_minimo')
            ->selectRaw("CASE WHEN ps.stock_actual = 0 THEN 'AGOTADO' WHEN ps.stock_actual <= ps.stock_minimo THEN 'BAJO' ELSE 'NORMAL' END as estado_stock")
            ->when($filters['id_categoria'] ?? null, fn ($q, $id) => $q->where('p.id_categoria', $id));
        $this->scopeBranches($query, 'ps.id_sucursal', $idSucursal);
        $query->when($idSucursal, fn ($q) => $q->where('ps.id_sucursal', $idSucursal));
        if (! empty($filters['estado_stock'])) {
            $query->whereRaw("CASE WHEN ps.stock_actual = 0 THEN 'AGOTADO' WHEN ps.stock_actual <= ps.stock_minimo THEN 'BAJO' ELSE 'NORMAL' END = ?", [$filters['estado_stock']]);
        }
        $rows = $query->orderBy('s.nombre')->orderBy('p.nombre')->get();

        return $this->payload('Existencias por sucursal', 'Consulta del stock de productos terminados por sucursal.', $filters, $rows, ['productos' => $rows->count(), 'agotados' => $rows->where('estado_stock', 'AGOTADO')->count(), 'bajos' => $rows->where('estado_stock', 'BAJO')->count()], $rows, $this->reportBranches(), [
            ['label' => 'Producto', 'key' => 'producto'], ['label' => 'Categoría', 'key' => 'categoria'], ['label' => 'Sucursal', 'key' => 'sucursal'], ['label' => 'Stock actual', 'key' => 'stock_actual'], ['label' => 'Stock mínimo', 'key' => 'stock_minimo'], ['label' => 'Estado', 'key' => 'estado_stock'],
        ], Categoria::where('activo', true)->orderBy('nombre')->get());
    }

    private function pagosData(Request $request): array
    {
        $this->authorizePermission('generar_reportes');
        $filters = $this->validatePeriod($request);
        $base = $this->salesBase($request, $filters)->join('venta_pago as vp', 'vp.id_venta', '=', 'v.id_venta')->join('metodos_pago as mp', 'mp.id_metodo_pago', '=', 'vp.id_metodo_pago');
        $aggregated = (clone $base)->select('mp.id_metodo_pago', 'mp.nombre')->selectRaw('COUNT(vp.id_venta_pago) operaciones, COALESCE(SUM(vp.importe), 0) total')->groupBy('mp.id_metodo_pago', 'mp.nombre')->get()->keyBy('id_metodo_pago');
        $grandTotal = (float) $aggregated->sum('total');
        $rows = MetodoPago::where('activo', true)->orderBy('nombre')->get()->map(function ($method) use ($aggregated, $grandTotal) {
            $item = $aggregated->get($method->id_metodo_pago);
            $row = (object) ['id_metodo_pago' => $method->id_metodo_pago, 'metodo' => $method->nombre, 'operaciones' => $item?->operaciones ?? 0, 'total' => (float) ($item?->total ?? 0)];
            $row->participacion = $grandTotal > 0 ? $row->total / $grandTotal * 100 : 0;
            return $row;
        });

        return $this->payload('Totales por método de pago', 'Importes registrados en venta_pago, incluyendo pagos mixtos sin duplicar ventas.', $filters, $rows, ['operaciones' => $rows->sum('operaciones'), 'total' => $grandTotal], $rows->map(fn ($r) => (object) ['nombre' => $r->metodo, 'total' => $r->total]), $this->reportBranches(), [
            ['label' => 'Método de pago', 'key' => 'metodo'], ['label' => 'Operaciones', 'key' => 'operaciones'], ['label' => 'Total recaudado', 'key' => 'total'], ['label' => 'Participación', 'key' => 'participacion'],
        ]);
    }

    private function cajasData(Request $request): array
    {
        $this->authorizePermission('generar_reportes');
        $filters = $request->validate(['id_sucursal' => ['nullable', 'integer'], 'desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date', 'after_or_equal:desde'], 'estado' => ['nullable', 'in:ABIERTA,CERRADA']]);
        $idSucursal = ! empty($filters['id_sucursal']) ? (int) $filters['id_sucursal'] : null;

        $query = Caja::query()
            ->with(['sucursal', 'usuarioApertura.personal'])
            ->when($filters['desde'] ?? null, fn ($q, $date) => $q->whereDate('cajas.fecha_apertura', '>=', $date))
            ->when($filters['hasta'] ?? null, fn ($q, $date) => $q->whereDate('cajas.fecha_apertura', '<=', $date))
            ->when($filters['estado'] ?? null, fn ($q, $state) => $q->where('cajas.estado', $state));

        $this->scopeBranches($query, 'cajas.id_sucursal', $idSucursal);
        $query->when($idSucursal, fn ($q) => $q->where('cajas.id_sucursal', $idSucursal));

        $cajas = $query->orderByDesc('cajas.fecha_apertura')->get();
        $resultados = CajaCalculadora::resultados($cajas);

        $rows = $cajas->map(function (Caja $caja) use ($resultados) {
            $resultado = $resultados[$caja->getKey()];
            $qrRegistrado = $resultado['qr_registrado'];

            return (object) [
                'id_caja' => $caja->getKey(),
                'sucursal' => $caja->sucursal?->nombre,
                'responsable' => $caja->usuarioApertura?->personal
                    ? trim($caja->usuarioApertura->personal->nombres.' '.$caja->usuarioApertura->personal->apellidos)
                    : ($caja->usuarioApertura?->nombre_usuario ?? '—'),
                'fecha_apertura' => $caja->fecha_apertura,
                'fecha_cierre' => $caja->fecha_cierre,
                'monto_inicial' => (float) $caja->monto_inicial,
                'total_ventas' => $resultado['vendido'] / 100,
                'efectivo_esperado' => $resultado['efectivo_esperado'] / 100,
                'efectivo_contado' => $caja->efectivo_contado === null ? null : (float) $caja->efectivo_contado,
                'diferencia_efectivo' => $resultado['diferencia_efectivo'] === null ? null : $resultado['diferencia_efectivo'] / 100,
                'qr_esperado' => $qrRegistrado ? $resultado['qr_esperado'] / 100 : null,
                'qr_verificado' => $resultado['qr_verificado'] === null ? null : $resultado['qr_verificado'] / 100,
                'diferencia_qr' => $resultado['diferencia_qr'] === null ? null : $resultado['diferencia_qr'] / 100,
                'diferencia_total' => $resultado['diferencia_total'] === null ? null : $resultado['diferencia_total'] / 100,
                'estado' => $caja->estado,
            ];
        });

        $data = $this->payload('Historial de caja', 'Consulta histórica de aperturas, cierres y arqueos de caja. El arqueo considera únicamente efectivo y QR; las transferencias no forman parte del arqueo.', $filters, $rows, ['cajas' => $rows->count(), 'abiertas' => $rows->where('estado', 'ABIERTA')->count(), 'cerradas' => $rows->where('estado', 'CERRADA')->count()], $rows, $this->reportBranches(), [
            ['label' => 'Número de caja', 'key' => 'id_caja'],
            ['label' => 'Sucursal', 'key' => 'sucursal'],
            ['label' => 'Cajero responsable', 'key' => 'responsable'],
            ['label' => 'Apertura', 'key' => 'fecha_apertura'],
            ['label' => 'Cierre', 'key' => 'fecha_cierre'],
            ['label' => 'Monto inicial', 'key' => 'monto_inicial'],
            ['label' => 'Total de ventas', 'key' => 'total_ventas'],
            ['label' => 'Efectivo esperado', 'key' => 'efectivo_esperado'],
            ['label' => 'Efectivo contado', 'key' => 'efectivo_contado'],
            ['label' => 'Diferencia de efectivo', 'key' => 'diferencia_efectivo'],
            ['label' => 'QR esperado', 'key' => 'qr_esperado'],
            ['label' => 'QR verificado', 'key' => 'qr_verificado'],
            ['label' => 'Diferencia de QR', 'key' => 'diferencia_qr'],
            ['label' => 'Diferencia total', 'key' => 'diferencia_total'],
            ['label' => 'Estado', 'key' => 'estado'],
        ]);

        $data['puedeVerDetalle'] = auth()->user()->roles()->where('nombre', 'Administrador')->exists();

        return $data;
    }

    private function payload(string $title, string $description, array $filters, $rows, array $stats, $json, $sucursales, array $columns, $categorias = null): array
    {
        return compact('title', 'description', 'filters', 'rows', 'stats', 'json', 'sucursales', 'columns', 'categorias') + ['generatedAt' => now()];
    }

    private function pdf(array $data, string $title, string $filename)
    {
        $data['title'] = $title;
        $data['pdfRows'] = $this->pdfRows($data['rows'], $data['columns']);
        $data['period'] = trim(($data['filters']['desde'] ?? 'Desde el inicio').' — '.($data['filters']['hasta'] ?? 'Hasta hoy'));
        $data['branchLabel'] = ! empty($data['filters']['id_sucursal'])
            ? optional($data['sucursales']->firstWhere('id_sucursal', (int) $data['filters']['id_sucursal']))->nombre
            : 'Todas las sucursales autorizadas';
        return Pdf::loadView('reportes.pdf', $data)->setPaper('a4', 'landscape')->download($filename.'.pdf');
    }

    private function pdfRows($rows, array $columns): array
    {
        return collect($rows)->map(fn ($row) => collect($columns)->mapWithKeys(fn ($column) => [$column['label'] => $this->pdfValue(data_get($row, $column['key']), $column['key'])])->all())->all();
    }

    private function pdfValue($value, string $key): string
    {
        if ($value === null || $value === '') return '—';
        if (str_contains($key, 'fecha')) return Carbon::parse($value)->timezone(config('app.timezone'))->format('d/m/Y H:i');
        if (in_array($key, self::MONEY_KEYS, true)) return 'Bs '.number_format((float) $value, 2, ',', '.');
        if ($key === 'participacion') return number_format((float) $value, 2, ',', '.').'%';
        return (string) $value;
    }
}
