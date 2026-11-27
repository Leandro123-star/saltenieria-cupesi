@extends('layouts.app')
@section('title', 'Corregir venta #'.$venta->id_venta)
@section('content')
<div class="heading">
    <div>
        <h2>Corregir venta #{{ $venta->id_venta }}</h2>
        <p class="muted">Sustituye productos registrados por error. La cantidad no se modifica. Para vender más productos, registra una nueva venta. Los cambios quedan registrados para auditoría.</p>
    </div>
    <div class="actions">
        <a class="btn out" href="{{ route('ventas.show', $venta) }}">← Volver al detalle</a>
    </div>
</div>

<div class="card">
    <h2>Datos de la venta</h2>
    <div class="grid grid3">
        <div><label>Sucursal</label><p>{{ $venta->sucursal?->nombre ?: '—' }}</p></div>
        <div><label>Cajero responsable</label><p>{{ $venta->usuario?->nombre_usuario ?: '—' }}</p></div>
        <div><label>Fecha y hora</label><p>{{ $venta->fecha_venta?->format('d/m/Y H:i') }}</p></div>
        <div><label>Caja</label><p>#{{ $venta->id_caja }} · {{ $venta->caja?->estado }}</p></div>
        <div><label>Total actual</label><p>Bs {{ number_format((float) $venta->total, 2, ',', '.') }}</p></div>
    </div>
</div>

<form method="POST" action="{{ route('ventas.corregir.update', $venta) }}">
    @csrf
    @method('PUT')

    <div class="card">
        <h2>Sustitución de productos</h2>
        <p class="muted">En la línea registrada por error, elige el producto correcto. Se conserva la cantidad original: no se añaden ni se quitan unidades.</p>
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Producto registrado</th>
                        <th>Sustituir por</th>
                        <th>Cantidad</th>
                        <th>Precio unitario</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody id="lines"></tbody>
            </table>
        </div>
        <div class="tot"><span>Total de la venta</span><span>Bs <b id="total">0.00</b></span></div>
    </div>

    <div class="card">
        <h2>Pagos registrados</h2>
        <p class="muted">Los pagos originales se conservan. Solo deberás ajustarlos si la sustitución cambia el total de la venta.</p>
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Método de pago</th>
                        <th>Importe pagado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($venta->pagos as $pago)
                        <tr>
                            <td>{{ $pago->metodoPago?->nombre ?: '—' }}</td>
                            <td>Bs {{ number_format((float) $pago->importe, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="muted">Sin pagos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="tot"><span>Total pagado</span><span>Bs {{ number_format((float) $venta->pagos->sum('importe'), 2, ',', '.') }}</span></div>
        <p id="difference" class="muted"></p>

        <div id="adjust" hidden>
            <h2 style="margin-top:16px">Ajustar pagos</h2>
            <p class="muted">La sustitución cambia el total. Registra cómo quedan los pagos después de cobrar o devolver la diferencia al cliente. Los pagos anteriores quedan en el historial de la corrección.</p>
            <div class="scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Método de pago</th>
                            <th>Importe</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="payments"></tbody>
                </table>
            </div>
            <div class="actions" style="margin-top:10px">
                <button class="btn out small" type="button" onclick="addPayment()">+ Añadir método</button>
            </div>
            <div class="tot"><span>Total de pagos ajustados</span><span>Bs <b id="paid">0.00</b></span></div>
        </div>
    </div>

    <div class="card">
        <h2>Motivo y confirmación</h2>
        <div class="f">
            <label for="motivo">Motivo de la corrección</label>
            <textarea id="motivo" name="motivo" rows="3" maxlength="500" required placeholder="Describa el error de registro que se está corrigiendo">{{ old('motivo') }}</textarea>
        </div>
        <label style="display:flex;gap:8px;align-items:flex-start;max-width:760px">
            <input type="checkbox" name="declaracion" value="1" style="width:auto;min-height:auto" required @checked(old('declaracion'))>
            <span>Confirmo que la corrección se debe a un error de registro y no a un arrepentimiento del cliente ni a un cambio solicitado después de confirmar la compra.</span>
        </label>
        <div class="actions" style="margin-top:16px">
            <button class="btn" type="submit" id="submit">Guardar corrección</button>
            <a class="btn out" href="{{ route('ventas.show', $venta) }}">Cancelar</a>
        </div>
    </div>
</form>

<script>
const products=@json($productosData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
const methods=@json($metodosData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
let lines=@json($detallesData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
const originalPayments=@json($pagosData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
let payments=null; // Ajuste de pagos: solo existe cuando la sustitución cambia el total.

const money=n=>Number(n||0).toFixed(2);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const productById=id=>products.find(p=>Number(p.id)===Number(id));
const methodById=id=>methods.find(m=>Number(m.id)===Number(id));

lines.forEach(l=>{l.original=Number(l.id_producto)});

// Precio de la línea: histórico si no cambia, vigente del sustituto si cambia.
function unitPrice(l){
  if(Number(l.id_producto)===l.original)return Number(l.precio_unitario);
  return Number(productById(l.id_producto)?.price||0);
}
// Unidades disponibles de un producto para la línea indicada: el stock de la
// sucursal más lo vendido en esta venta (p.stock), menos lo que ocupan las demás líneas.
function available(id,index){
  const p=productById(id);if(!p)return 0;
  return lines.reduce((stock,l,i)=>i!==index&&Number(l.id_producto)===Number(id)?stock-Number(l.cantidad):stock,Number(p.stock));
}

const cents=n=>Math.round(Number(n||0)*100);
function total(){return lines.reduce((sum,l)=>sum+cents(unitPrice(l))*Number(l.cantidad||0),0)}
function sumPayments(list){return list.reduce((sum,p)=>sum+cents(p.importe),0)}
const originalPaid=sumPayments(originalPayments);

function updateTotals(){
  const t=total();
  document.getElementById('total').textContent=money(t/100);
  const adjust=document.getElementById('adjust');
  const el=document.getElementById('difference');
  const submit=document.getElementById('submit');
  if(t===originalPaid){
    // Sin diferencia: no se envía ningún pago y se conservan los registrados.
    if(payments!==null){payments=null;document.getElementById('payments').innerHTML=''}
    adjust.hidden=true;
    el.textContent='Los pagos registrados cubren el total; se conservarán sin cambios.';
    el.style.color='';
    submit.disabled=false;
    return;
  }
  if(payments===null){payments=originalPayments.map(p=>({...p}));adjust.hidden=false;renderPayments();return}
  const base=t-originalPaid;
  const pending=t-sumPayments(payments);
  el.textContent=(base>0?`La sustitución aumenta el total: cobrar al cliente Bs ${money(base/100)}.`:`La sustitución reduce el total: devolver al cliente Bs ${money(-base/100)}.`)
    +(pending===0?' Los pagos ajustados coinciden con el nuevo total.':` Faltan ajustar Bs ${money(Math.abs(pending)/100)} ${pending>0?'(por cobrar)':'(por devolver)'}.`);
  el.style.color=pending===0?'':'#b42318';
  document.getElementById('paid').textContent=money(sumPayments(payments)/100);
  submit.disabled=pending!==0;
}
function renderLines(){
  document.getElementById('lines').innerHTML=lines.map((line,index)=>{
    const qty=Number(line.cantidad);
    const options=[`<option value="${line.original}" ${Number(line.id_producto)===line.original?'selected':''}>Sin cambio (${esc(line.nombre)})</option>`]
      .concat(products.filter(p=>Number(p.id)!==line.original).map(p=>{
        const stock=available(p.id,index);
        const disabled=stock<qty&&Number(p.id)!==Number(line.id_producto);
        return `<option value="${p.id}" ${Number(p.id)===Number(line.id_producto)?'selected':''} ${disabled?'disabled':''}>${esc(p.name)} · Bs ${money(p.price)} · stock ${Math.max(stock,0)}${disabled?' (insuficiente)':''}</option>`;
      })).join('');
    const unit=unitPrice(line);
    const changed=Number(line.id_producto)!==line.original;
    return `<tr>
      <td>${esc(line.nombre)}${changed?` <span class="pill">Se sustituye</span>`:''}</td>
      <td>
        <input type="hidden" name="detalles[${index}][id_detalle_venta]" value="${line.id_detalle_venta}">
        <select name="detalles[${index}][id_producto]" onchange="changeProduct(${index},this.value)">${options}</select>
      </td>
      <td>${qty} <span class="muted">(original)</span></td>
      <td>Bs ${money(unit)}</td>
      <td>Bs ${money(unit*qty)}</td>
    </tr>`;
  }).join('');
  updateTotals();
}

function changeProduct(index,value){lines[index].id_producto=Number(value);renderLines()}

function renderPayments(){
  document.getElementById('payments').innerHTML=(payments||[]).map((payment,index)=>{
    const options=methods.map(m=>`<option value="${m.id}" ${Number(m.id)===Number(payment.id_metodo_pago)?'selected':''}>${esc(m.name)}</option>`).join('');
    return `<tr>
      <td><select name="pagos[${index}][id_metodo_pago]" onchange="changeMethod(${index},this.value)">${options}</select></td>
      <td><input type="hidden" name="pagos[${index}][verificado]" value="${payment.verificado?'1':'0'}"><input type="number" name="pagos[${index}][importe]" step="0.01" min="0.01" max="999999.99" inputmode="decimal" title="Importe mayor que cero" required value="${money(payment.importe)}" style="width:130px" oninput="changeAmount(${index},this.value)"></td>
      <td>${payments.length>1?`<button type="button" class="btn out small" onclick="removePayment(${index})" aria-label="Quitar pago">×</button>`:''}</td>
    </tr>`;
  }).join('');
  updateTotals();
}

function changeMethod(index,value){payments[index].id_metodo_pago=Number(value)}
function changeAmount(index,value){payments[index].importe=Number(value);updateTotals()}
function addPayment(){payments.push({id_metodo_pago:Number(methods[0]?.id||0),importe:0,verificado:false});renderPayments()}
function removePayment(index){if(payments.length>1){payments.splice(index,1);renderPayments()}}

renderLines();
</script>
@endsection
