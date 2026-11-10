<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'Cupesí') · Cupesí</title>
    <style>
        :root{--pri:#cc4a05;--hover:#a63c03;--gold:#ffc857;--bg:#f1f0ed;--card:#fff;--line:#e3ded6;--tx:#231a15;--muted:#6c6159}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--tx);font:15px/1.5 Arial,sans-serif}.app{display:grid;grid-template-columns:217px 1fr;min-height:100vh}.side{background:#cf4800;color:#fff1e6;padding:22px 12px 16px;position:sticky;top:0;height:100vh;display:flex;flex-direction:column;gap:2px}.brand{font-size:24px;font-weight:700;padding:10px}.brand small{display:block;font-size:13px}.side a{color:inherit;text-decoration:none;padding:10px 11px;border-left:3px solid transparent;border-radius:0 9px 9px 0;display:block}.side a:hover,.side a.on{background:#fff;color:#a43b00;border-left-color:var(--gold);font-weight:700}.sidebar-foot{margin-top:auto;border-top:1px solid #ffffff44;padding:14px 10px 0;font-size:12px}.top{position:sticky;top:0;z-index:5;background:var(--bg);border-bottom:3px solid var(--pri);padding:12px 24px;display:flex;align-items:center;gap:12px}.top h1{font-size:23px;margin:0;flex:1}.chip{background:#fbefd0;color:#7a4f00;border-radius:99px;padding:4px 12px;font-size:13px;font-weight:600}.content{padding:22px 24px 40px}.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:18px;margin-bottom:14px}.grid{display:grid;gap:14px}.grid2{grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}.grid3{grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}.split{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,1fr);gap:14px}.heading{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px}.heading h2,h2{font-size:20px;margin:0 0 12px}label{display:block;color:var(--muted);font-size:13px;margin-bottom:4px}input,select,textarea{width:100%;min-height:42px;padding:8px 11px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--tx);font:inherit}textarea{min-height:80px}.formgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}.f{margin-bottom:12px}.btn{display:inline-flex;align-items:center;justify-content:center;background:var(--pri);color:#fff;border:1px solid var(--pri);border-radius:8px;min-height:42px;padding:8px 16px;font-weight:600;text-decoration:none;cursor:pointer}.btn:hover{background:var(--hover)}.btn.out{background:transparent;color:var(--pri)}.btn.small{min-height:33px;padding:5px 10px;font-size:13px}.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.notice,.success,.error{padding:11px 14px;border-radius:9px;margin-bottom:14px}.notice{background:#fbefd0;color:#7a4f00}.success{background:#e3f2e8;color:#2f7d4f}.error{background:#fdecea;color:#b42318}.errors{font-size:13px;margin:0}table{width:100%;border-collapse:collapse;font-size:14px}th{text-align:left;color:var(--muted);font-size:13px;padding:9px;border-bottom:1px solid var(--line)}td{padding:10px 9px;border-bottom:1px solid var(--line)}tr:hover td{background:#fbf4ee}.scroll{overflow:auto}.pill{display:inline-block;padding:2px 10px;border-radius:99px;background:#e3f2e8;color:#2f7d4f;font-size:12px;font-weight:600}.pill.off{background:#fdecea;color:#b42318}.muted{color:var(--muted)}.product-img{width:54px;height:46px;object-fit:cover;border-radius:8px;border:1px solid var(--line)}.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}.product-card{border:1px solid var(--line);border-radius:10px;padding:8px;background:#fff}.product-card img{width:100%;height:90px;object-fit:cover;border-radius:8px}.product-card button{width:100%;border:0;background:none;text-align:left;cursor:pointer;font-weight:600;padding:5px 0}.cart-line{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line);padding:10px 0;gap:8px}.tot{display:flex;justify-content:space-between;font-size:20px;font-weight:700;padding:14px}@media(max-width:900px){.app{display:block}.side{position:fixed;inset:auto 0 0;height:auto;z-index:8;flex-direction:row;overflow:auto;padding:6px}.brand,.sidebar-foot{display:none}.side a{white-space:nowrap;border:0;border-radius:8px;font-size:12px;padding:7px}.top,.content{padding-left:14px;padding-right:14px}.top .chip{display:none}.split{grid-template-columns:1fr}}
    </style>
</head>
<body>
@php
    $permissions = auth()->user()->roles->where('activo', true)->flatMap->permisos->pluck('nombre')->unique();
    $admin = auth()->user()->roles->contains('nombre', 'Administrador');
    $cajero = auth()->user()->roles->whereIn('nombre', ['Cajero', 'Cajero/Vendedor'])->isNotEmpty();
@endphp
<div class="app">
    <nav class="side">
        <div class="brand">Cupesí<small>Salteñería</small></div>
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}">▦ &nbsp; Panel</a>
        @if($admin || $permissions->contains('registrar_ventas'))
            <a href="{{ route('ventas.create') }}" class="{{ request()->routeIs('ventas.*') ? 'on' : '' }}">🛒 &nbsp; Ventas</a>
        @endif
        @if($admin || $permissions->contains('gestionar_productos'))
            <a href="{{ route('productos.index') }}" class="{{ request()->routeIs('productos.*') ? 'on' : '' }}">◇ &nbsp; Productos</a>
        @endif
        @if($admin || $permissions->contains('gestionar_categorias'))
            <a href="{{ route('categorias.index') }}" class="{{ request()->routeIs('categorias.*') ? 'on' : '' }}">▤ &nbsp; Categorías</a>
        @endif
        @if($admin || $permissions->contains('gestionar_metodos_pago'))
            <a href="{{ route('metodos-pago.index') }}" class="{{ request()->routeIs('metodos-pago.*') ? 'on' : '' }}">▤ &nbsp; Métodos de Pago</a>
        @endif
        @if($admin || $permissions->contains('gestionar_caja'))
            <a href="{{ route('cajas.index') }}" class="{{ request()->routeIs('cajas.*') ? 'on' : '' }}">▣ &nbsp; Caja</a>
        @endif
        @if($admin || $permissions->contains('gestionar_sucursales'))
            <a href="{{ route('sucursales.index') }}" class="{{ request()->routeIs('sucursales.*') ? 'on' : '' }}">⌂ &nbsp; Sucursales</a>
        @endif
        @if($admin || $permissions->contains('gestionar_personal'))
            <a href="{{ route('personal.index') }}" class="{{ request()->routeIs('personal.*') ? 'on' : '' }}">♙ &nbsp; Personal</a>
        @endif
        @if($admin || $permissions->contains('gestionar_usuarios'))
            <a href="{{ route('usuarios.index') }}" class="{{ request()->routeIs('usuarios.*') ? 'on' : '' }}">♟ &nbsp; Usuarios</a>
        @endif
        @if($admin || $permissions->contains('gestionar_roles'))
            <a href="{{ route('roles.index') }}" class="{{ request()->routeIs('roles.*') ? 'on' : '' }}">⬡ &nbsp; Roles</a>
        @endif
        @if($admin || $permissions->contains('gestionar_permisos'))
            <a href="{{ route('permisos.index') }}" class="{{ request()->routeIs('permisos.*') ? 'on' : '' }}">⚿ &nbsp; Permisos</a>
        @endif
        @if($admin || $permissions->contains('generar_reportes'))
            <details class="reports-menu" {{ request()->routeIs('reportes.*') ? 'open' : '' }}>
                <summary>▥ &nbsp; Reportes</summary>
                <a href="{{ route('reportes.ventas-periodo') }}" class="{{ request()->routeIs('reportes.ventas-periodo') ? 'on' : '' }}">Ventas por período</a>
                <a href="{{ route('reportes.ventas-sucursal') }}" class="{{ request()->routeIs('reportes.ventas-sucursal') ? 'on' : '' }}">Ventas por sucursal</a>
                <a href="{{ route('reportes.productos-mas-vendidos') }}" class="{{ request()->routeIs('reportes.productos-mas-vendidos') ? 'on' : '' }}">Productos más vendidos</a>
                <a href="{{ route('reportes.existencias') }}" class="{{ request()->routeIs('reportes.existencias') ? 'on' : '' }}">Existencias</a>
                <a href="{{ route('reportes.pagos') }}" class="{{ request()->routeIs('reportes.pagos') ? 'on' : '' }}">Pagos</a>
                <a href="{{ route('reportes.cajas') }}" class="{{ request()->routeIs('reportes.cajas') ? 'on' : '' }}">Cajas</a>
            </details>
        @endif
        <div class="sidebar-foot">{{ auth()->user()->personal?->nombres ?? auth()->user()->nombre_usuario }}<br>{{ auth()->user()->roles->pluck('nombre')->join(' · ') }}<br>{{ auth()->user()->sucursales->firstWhere('id_sucursal', session('sucursal_activa_id'))?->nombre }}</div>
    </nav>
    <main>
        <header class="top">
            <h1>@yield('title', 'Cupesí')</h1>
            @if(session('sucursal_activa_id'))
                <span class="chip">{{ auth()->user()->sucursales->firstWhere('id_sucursal', session('sucursal_activa_id'))?->nombre }}</span>
                @if(!$cajero)<a class="btn out small" href="{{ route('branch.choose') }}">Cambiar sucursal</a>@endif
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn out small">Cerrar sesión</button></form>
        </header>
        <div class="content">
            @if(session('success'))
                <div class="success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="error">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="error"><ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
