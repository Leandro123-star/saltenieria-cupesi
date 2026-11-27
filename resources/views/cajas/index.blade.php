@extends('layouts.app')
@section('title','Caja')
@section('content')
<div class="heading"><h2>Operación caja</h2></div>
<div class="card"><h2>Abrir caja</h2><form method="POST" action="{{ route('cajas.abrir') }}" class="formgrid">@csrf
    <div class="f"><label>Sucursal</label><select name="id_sucursal" required>@foreach($sucursales as $sucursal)<option value="{{ $sucursal->id_sucursal }}" @selected(session('sucursal_activa_id')==$sucursal->id_sucursal)>{{ $sucursal->nombre }}</option>@endforeach</select></div>
    <div class="f"><label>Monto inicial (Bs)</label><input name="monto_inicial" type="number" min="0" max="999999.99" step="0.01" inputmode="decimal" value="{{ old('monto_inicial', 0) }}" required title="Monto de 0 o mayor, con hasta 2 decimales" @class(['is-invalid' => $errors->has('monto_inicial')])></div>
    <div class="actions"><button class="btn">Abrir caja</button></div>
</form></div>
<div class="card"><h2>Cajas registradas</h2><form class="formgrid" method="GET">
    <div class="f"><label>Sucursal</label><select name="id_sucursal"><option value="">Todas</option>@foreach($sucursales as $sucursal)<option value="{{ $sucursal->id_sucursal }}" @selected(request('id_sucursal')==$sucursal->id_sucursal)>{{ $sucursal->nombre }}</option>@endforeach</select></div>
    <div class="f"><label>Desde</label><input type="date" name="desde" value="{{ request('desde') }}"></div><div class="f"><label>Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}"></div>
    <div class="f"><label>Estado</label><select name="estado"><option value="">Todos</option><option value="ABIERTA" @selected(request('estado')==='ABIERTA')>Abierta</option><option value="CERRADA" @selected(request('estado')==='CERRADA')>Cerrada</option></select></div>
    <div class="actions"><button class="btn">Filtrar</button><a class="btn out" href="{{ route('cajas.index') }}">Limpiar</a></div>
</form></div>
<div class="card scroll"><table><tr><th>N°</th><th>Sucursal</th><th>Cajero</th><th>Apertura</th><th>Monto inicial</th><th>Estado</th><th>Acción</th></tr>
@forelse($cajas as $caja)<tr><td>{{ $caja->id_caja }}</td><td>{{ $caja->sucursal?->nombre }}</td><td>{{ $caja->usuarioApertura?->persona ? trim($caja->usuarioApertura->persona->nombres.' '.$caja->usuarioApertura->persona->apellidos) : ($caja->usuarioApertura?->nombre_usuario ?? '—') }}</td><td>{{ $caja->fecha_apertura?->format('d/m/Y H:i') }}</td><td>Bs {{ number_format($caja->monto_inicial,2,',','.') }}</td><td><span class="pill {{ $caja->estado==='ABIERTA'?'':'off' }}">{{ $caja->estado }}</span></td><td class="actions">
    @if($caja->estado==='ABIERTA')<form method="POST" action="{{ route('cajas.cerrar',$caja) }}" class="actions">@csrf<input name="efectivo_contado" type="number" min="0" max="999999.99" step="0.01" inputmode="decimal" placeholder="Efectivo contado" aria-label="Efectivo contado" title="Monto de 0 o mayor" required style="max-width:140px" @class(['is-invalid' => $errors->has('efectivo_contado')])><input name="qr_verificado" type="number" min="0" max="999999.99" step="0.01" inputmode="decimal" placeholder="QR verificado" aria-label="QR verificado" title="Monto de 0 o mayor" required style="max-width:140px" @class(['is-invalid' => $errors->has('qr_verificado')])><button class="btn out small">Cerrar</button></form>@endif
</td></tr>@empty<tr><td colspan="7" class="muted">No hay cajas registradas.</td></tr>@endforelse</table>{{ $cajas->links() }}</div>
@endsection
