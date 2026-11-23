@extends('layouts.app')
@section('title','Historial de caja')
@section('content')
@include('reportes._styles')
@php
    $money = fn ($value): string => $value === null ? '—' : 'Bs '.number_format((float) $value, 2, ',', '.');
    $date = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—';
@endphp
<div class="heading"><div><h2>Historial de caja</h2></div><a class="btn" target="_blank" href="{{ route('reportes.cajas.pdf', request()->query()) }}">Exportar PDF</a></div>
<div class="card"><form method="GET" class="formgrid"><div class="f"><label>Sucursal</label><select name="id_sucursal"><option value="">Todas</option>@foreach($sucursales as $sucursal)<option value="{{ $sucursal->id_sucursal }}" @selected(($filters['id_sucursal'] ?? '')==$sucursal->id_sucursal)>{{ $sucursal->nombre }}</option>@endforeach</select></div><div class="f"><label>Fecha desde</label><input type="date" name="desde" value="{{ $filters['desde'] ?? '' }}"></div><div class="f"><label>Fecha hasta</label><input type="date" name="hasta" value="{{ $filters['hasta'] ?? '' }}"></div><div class="f"><label>Estado</label><select name="estado"><option value="">Todos</option><option value="ABIERTA" @selected(($filters['estado'] ?? '')==='ABIERTA')>Abierta</option><option value="CERRADA" @selected(($filters['estado'] ?? '')==='CERRADA')>Cerrada</option></select></div><div class="actions"><button class="btn">Filtrar</button><a class="btn out" href="{{ route('reportes.cajas') }}">Limpiar</a></div></form></div>
<div class="grid grid3"><div class="card"><div class="metric-label">Cajas consultadas</div><div class="metric">{{ $stats['cajas'] }}</div></div><div class="card"><div class="metric-label">Abiertas</div><div class="metric">{{ $stats['abiertas'] }}</div></div><div class="card"><div class="metric-label">Cerradas</div><div class="metric">{{ $stats['cerradas'] }}</div></div></div>
<div class="card scroll"><table><tr><th>N°</th><th>Sucursal</th><th>Cajero</th><th>Apertura</th><th>Monto inicial</th><th>Estado</th>@if($puedeVerDetalle ?? false)<th>Acción</th>@endif</tr>
@forelse($rows as $row)<tr><td>{{ $row->id_caja }}</td><td>{{ $row->sucursal }}</td><td>{{ $row->responsable }}</td><td>{{ $date($row->fecha_apertura) }}</td><td>{{ $money($row->monto_inicial) }}</td><td><span class="pill {{ $row->estado==='ABIERTA'?'':'off' }}">{{ $row->estado }}</span></td>@if($puedeVerDetalle ?? false)<td><a class="btn out small" href="{{ route('cajas.show',$row->id_caja) }}">Ver detalle</a></td>@endif</tr>@empty<tr><td colspan="{{ ($puedeVerDetalle ?? false) ? 7 : 6 }}" class="muted">No existen cajas para los filtros seleccionados.</td></tr>@endforelse</table></div>
@endsection
