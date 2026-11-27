<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'Cupesí') · Cupesí</title>
    <style>
        :root{--pri:#cc4a05;--hover:#a63c03;--gold:#ffc857;--bg:#f1f0ed;--card:#fff;--line:#e3ded6;--tx:#231a15;--muted:#6c6159}
        *{box-sizing:border-box}
        html{min-height:100%;background:var(--bg)}
        body{min-height:100%;margin:0;background:var(--bg);color:var(--tx);font:15px/1.5 Arial,sans-serif;overflow-x:hidden}
        .app{display:grid;grid-template-columns:217px minmax(0,1fr);min-height:100dvh}
        .side{background:#cf4800;color:#fff1e6;padding:22px 12px 16px;position:sticky;top:0;height:100dvh;min-height:0;max-height:100dvh;display:flex;flex-direction:column;overflow:hidden;z-index:20}
        .brand{flex:0 0 auto;font-size:24px;font-weight:700;padding:10px}
        .brand small{display:block;font-size:13px}
        .sidebar-scroll{min-height:0;flex:1 1 auto;overflow-y:auto;overflow-x:hidden;padding:2px 0 10px;scrollbar-width:thin;scrollbar-color:#ffffff66 transparent}
        .sidebar-scroll::-webkit-scrollbar{width:7px}
        .sidebar-scroll::-webkit-scrollbar-thumb{background:#ffffff66;border-radius:99px}
        .side a{color:inherit;text-decoration:none;padding:10px 11px;border-left:3px solid transparent;border-radius:0 9px 9px 0;display:block;overflow-wrap:anywhere}
        .side a:hover,.side a.on{background:#fff;color:#a43b00;border-left-color:var(--gold);font-weight:700}
        .reports-menu{margin:0}
        .reports-menu summary{cursor:pointer;list-style:none;color:inherit;padding:10px 11px;border-left:3px solid transparent;border-radius:0 9px 9px 0;user-select:none}
        .reports-menu summary::-webkit-details-marker{display:none}
        .reports-menu summary::after{content:'⌄';float:right;transition:transform .15s ease}
        .reports-menu[open] summary::after{transform:rotate(180deg)}
        .reports-menu summary:hover,.reports-menu[open] summary{background:#b83f02}
        .reports-menu>a{padding-left:28px;font-size:14px}
        .sidebar-foot{flex:0 0 auto;border-top:1px solid #ffffff44;padding:14px 10px 0;font-size:12px;overflow-wrap:anywhere}
        main{min-width:0}
        .top{position:sticky;top:0;z-index:5;background:var(--bg);border-bottom:3px solid var(--pri);padding:12px 24px;display:flex;align-items:center;gap:12px}
        .top h1{font-size:23px;margin:0;flex:1;min-width:0}
        .chip{background:#fbefd0;color:#7a4f00;border-radius:99px;padding:4px 12px;font-size:13px;font-weight:600}
        .menu-toggle{display:none;background:transparent;color:var(--pri);border:1px solid var(--pri);border-radius:8px;min-width:40px;height:40px;font-size:21px;cursor:pointer}
        .content{padding:22px 24px 40px}
        .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:18px;margin-bottom:14px}
        .grid{display:grid;gap:14px}.grid2{grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}.grid3{grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
        .split{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,1fr);gap:14px}
        .heading{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px}
        .heading h2,h2{font-size:20px;margin:0 0 12px}
        label{display:block;color:var(--muted);font-size:13px;margin-bottom:4px}
        input,select,textarea{width:100%;min-height:42px;padding:8px 11px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--tx);font:inherit}
        textarea{min-height:80px}.formgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}.f{margin-bottom:12px}
        .btn{display:inline-flex;align-items:center;justify-content:center;background:var(--pri);color:#fff;border:1px solid var(--pri);border-radius:8px;min-height:42px;padding:8px 16px;font-weight:600;text-decoration:none;cursor:pointer}.btn:hover{background:var(--hover)}.btn.out{background:transparent;color:var(--pri)}.btn.small{min-height:33px;padding:5px 10px;font-size:13px}
        .actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.notice,.success,.error{padding:11px 14px;border-radius:9px;margin-bottom:14px}.notice{background:#fbefd0;color:#7a4f00}.success{background:#e3f2e8;color:#2f7d4f}.error{background:#fdecea;color:#b42318}.errors{font-size:13px;margin:0}
        table{width:100%;border-collapse:collapse;font-size:14px}th{text-align:left;color:var(--muted);font-size:13px;padding:9px;border-bottom:1px solid var(--line)}td{padding:10px 9px;border-bottom:1px solid var(--line)}tr:hover td{background:#fbf4ee}.scroll{overflow:auto}.pill{display:inline-block;padding:2px 10px;border-radius:99px;background:#e3f2e8;color:#2f7d4f;font-size:12px;font-weight:600}.pill.off{background:#fdecea;color:#b42318}.muted{color:var(--muted)}
        .product-img{width:54px;height:46px;object-fit:cover;border-radius:8px;border:1px solid var(--line)}.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}.product-card{border:1px solid var(--line);border-radius:10px;padding:8px;background:#fff}.product-card img{width:100%;height:90px;object-fit:cover;border-radius:8px}.product-card button{width:100%;border:0;background:none;text-align:left;cursor:pointer;font-weight:600;padding:5px 0}.cart-line{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line);padding:10px 0;gap:8px}.tot{display:flex;justify-content:space-between;font-size:20px;font-weight:700;padding:14px}
        .pager{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:10px;margin-top:14px;font-size:14px}
        .pager-info{margin:0}
        .pager-links{display:flex;flex-wrap:wrap;gap:6px}
        .pager-links a,.pager-links span{min-width:36px;min-height:36px;padding:6px 11px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--pri);text-decoration:none;display:inline-flex;align-items:center;justify-content:center;font-weight:600;line-height:1}
        .pager-links a:hover{background:#fbf4ee;border-color:var(--pri)}
        .pager-links .on{background:var(--pri);border-color:var(--pri);color:#fff}
        .pager-links .off{color:var(--muted);opacity:.55;cursor:default}
        .pager-links .gap{border-color:transparent;background:transparent;opacity:1}
        .sidebar-overlay{display:none}
        dialog.modal{width:min(460px,92vw);max-height:90dvh;overflow:auto;border:1px solid var(--line);border-radius:14px;padding:20px;background:var(--card);color:var(--tx);box-shadow:0 18px 50px #0003}
        dialog.modal::backdrop{background:#0007}
        dialog.modal h2{margin:0 0 14px}
        dialog.modal input[readonly]{background:#fbf4ee;color:var(--muted)}
        .modal-actions{justify-content:flex-end;margin-top:6px}
        @media(max-width:900px){
            .app{display:block;min-height:100dvh}
            .side{position:fixed;inset:0 auto 0 0;width:min(300px,85vw);height:100dvh;max-height:none;transform:translateX(-100%);transition:transform .2s ease;box-shadow:8px 0 24px #0003}
            .side.is-open{transform:translateX(0)}
            .sidebar-overlay{position:fixed;inset:0;background:#0007;z-index:19}
            .sidebar-overlay.is-visible{display:block}
            .menu-toggle{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto}
            .top,.content{padding-left:14px;padding-right:14px}
            .top .chip{display:none}.split{grid-template-columns:1fr}
        }
        @media(max-width:480px){.top{padding-top:9px;padding-bottom:9px}.top h1{font-size:19px}.content{padding-top:14px}.heading{align-items:flex-start;flex-wrap:wrap}.pager{flex-direction:column;align-items:flex-start}}
    </style>
</head>
<body>
@php
    $rol = auth()->user()->rol;
    $permissions = $rol && $rol->activo ? $rol->permisos->pluck('clave')->unique() : collect();
    $admin = $rol?->nombre === 'Administrador';
    $cajero = in_array($rol?->nombre, ['Cajero', 'Cajero/Vendedor'], true);
    $moduleHeaders = [
        'dashboard' => 'Panel principal',
        'ventas.*' => 'Gestionar ventas',
        'productos.*' => 'Gestionar productos',
        'inventario.*' => 'Gestionar productos',
        'categorias.*' => 'Gestionar categorías',
        'metodos-pago.*' => 'Gestionar métodos de pago',
        'cajas.*' => 'Gestionar caja',
        'sucursales.*' => 'Gestionar sucursales',
        'personas.*' => 'Gestionar personas',
        'usuarios.*' => 'Gestionar usuarios',
        'roles.*' => 'Gestionar roles',
        'permisos.*' => 'Gestionar permisos',
        'reportes.*' => 'Reportes',
    ];
    $moduleHeader = collect($moduleHeaders)->first(fn ($label, $pattern) => request()->routeIs($pattern));
@endphp
<div class="app">
    <nav class="side" id="sidebar" aria-label="Menú principal">
        <div class="brand">Cupesí<small>Salteñería</small></div>
        <div class="sidebar-scroll">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}">▦ &nbsp; Panel</a>
            @if($admin || $permissions->contains('registrar_ventas'))
                <a href="{{ route('ventas.index') }}" class="{{ request()->routeIs('ventas.*') ? 'on' : '' }}">🛒 &nbsp; Ventas</a>
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
            @if($admin || $permissions->contains('gestionar_personas'))
                <a href="{{ route('personas.index') }}" class="{{ request()->routeIs('personas.*') ? 'on' : '' }}">♙ &nbsp; Personas</a>
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
                    <a href="{{ route('reportes.existencias') }}" class="{{ request()->routeIs('reportes.existencias') ? 'on' : '' }}">Existencias por sucursal</a>
                    <a href="{{ route('reportes.pagos') }}" class="{{ request()->routeIs('reportes.pagos') ? 'on' : '' }}">Totales por método de pago</a>
                    <a href="{{ route('reportes.cajas') }}" class="{{ request()->routeIs('reportes.cajas') ? 'on' : '' }}">Historial de caja</a>
                </details>
            @endif
        </div>
        <div class="sidebar-foot">{{ auth()->user()->persona?->nombres ?? auth()->user()->nombre_usuario }}<br>{{ auth()->user()->rol?->nombre }}<br>{{ auth()->user()->sucursales->firstWhere('id_sucursal', session('sucursal_activa_id'))?->nombre }}</div>
    </nav>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <main>
        <header class="top">
            <button class="menu-toggle" id="sidebarToggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="sidebar">☰</button>
            <h1>
                @hasSection('header')
                    @yield('header')
                @elseif($moduleHeader)
                    {{ $moduleHeader }}
                @else
                    @yield('title', 'Cupesí')
                @endif
            </h1>
            @if(session('sucursal_activa_id'))
                <span class="chip">{{ auth()->user()->sucursales->firstWhere('id_sucursal', session('sucursal_activa_id'))?->nombre }}</span>
                @if(!$cajero)<a class="btn out small" href="{{ route('branch.choose') }}">Cambiar sucursal</a>@endif
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn out small">Cerrar sesión</button></form>
        </header>
        <div class="content">
            @if(session('success'))<div class="success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="error">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="error"><ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div>
    </main>
</div>
<script>
    (() => {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');
        if (!sidebar || !toggle || !overlay) return;
        const closeSidebar = () => {
            sidebar.classList.remove('is-open');
            overlay.classList.remove('is-visible');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Abrir menú');
        };
        toggle.addEventListener('click', () => {
            const open = sidebar.classList.toggle('is-open');
            overlay.classList.toggle('is-visible', open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
        });
        overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', closeSidebar));
        window.addEventListener('resize', () => { if (window.innerWidth > 900) closeSidebar(); });
    })();
</script>
</body>
</html>
