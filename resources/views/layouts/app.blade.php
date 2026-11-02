<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'KS Gestion') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/ks-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <img src="{{ asset('images/ks-logo.png') }}" alt="KS">
            <div><strong>KS Gestion</strong><small>Groupe multi-filiales</small></div>
        </div>

        <div class="nav-section">Alertes</div>
        <a class="nav-link {{ request()->routeIs('alerts.*') ? 'active' : '' }}" href="{{ route('alerts.index') }}"><span class="nav-icon">AL</span> Alertes actives</a>

        @if(in_array(auth()->user()->role, [\App\Enums\UserRole::ADMIN, \App\Enums\UserRole::CASHIER], true))
            <div class="nav-section">Vendre</div>
            <a class="nav-link {{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.index') }}"><span class="nav-icon">VE</span> Ventes</a>
            <a class="nav-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}" href="{{ route('invoices.index') }}"><span class="nav-icon">FA</span> Factures</a>

            <div class="nav-section">Encaisser</div>
            <a class="nav-link {{ request()->routeIs('cash.*') ? 'active' : '' }}" href="{{ route('cash.index') }}"><span class="nav-icon">CA</span> Caisse</a>
        @endif

        @if(in_array(auth()->user()->role, [\App\Enums\UserRole::ADMIN, \App\Enums\UserRole::STOCKKEEPER], true))
            <div class="nav-section">Stock</div>
            <a class="nav-link {{ request()->routeIs('stock.*') ? 'active' : '' }}" href="{{ route('stock.index') }}"><span class="nav-icon">ST</span> Stock</a>
            @if(auth()->user()->role === \App\Enums\UserRole::ADMIN)
                <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}"><span class="nav-icon">PR</span> Produits</a>
            @endif
        @endif

        @if(auth()->user()->role === \App\Enums\UserRole::ADMIN)
            <div class="nav-section">Clients</div>
            <a class="nav-link {{ request()->routeIs('clients.*') ? 'active' : '' }}" href="{{ route('clients.index') }}"><span class="nav-icon">CL</span> Clients</a>

            <div class="nav-section">Pilotage</div>
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-icon">DB</span> Tableau de bord</a>
            <a class="nav-link {{ request()->routeIs('accounting.*') ? 'active' : '' }}" href="{{ route('accounting.index') }}"><span class="nav-icon">OH</span> SYSCOHADA</a>
            <a class="nav-link {{ request()->routeIs('subsidiaries.*') ? 'active' : '' }}" href="{{ route('subsidiaries.index') }}"><span class="nav-icon">FI</span> Filiales</a>
            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><span class="nav-icon">US</span> Utilisateurs</a>
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.edit') }}"><span class="nav-icon">PA</span> Paramètres</a>
        @endif
    </aside>

    <main class="main">
        <header class="topbar no-print">
            <div style="display:flex;align-items:center;gap:12px">
                <button class="mobile-toggle" data-sidebar-toggle>☰</button>
                <h1 class="page-title">@yield('page-title', 'KS Gestion')</h1>
            </div>
            <div class="topbar-user">
                <div class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</div>
                <div class="user-meta"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role->label() }}</small></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary btn-sm" type="submit">Déconnexion</button></form>
            </div>
        </header>
        <section class="content">
            @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
            @if($errors->any())
                <div class="alert alert-danger"><strong>Veuillez corriger les points suivants :</strong><ul style="margin:6px 0 0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </section>
    </main>
</div>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
