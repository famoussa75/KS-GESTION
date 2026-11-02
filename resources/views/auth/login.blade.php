<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — KS Gestion</title>
    <link rel="icon" href="{{ asset('images/ks-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="login-page">
    <section class="login-brand">
        <img src="{{ asset('images/ks-logo.png') }}" alt="KS Groupe">
        <h1>Gestion unifiée. Pilotage précis.</h1>
        <p>Facturation, caisse, stock, commandes et pré-comptabilité structurés par filiale, avec une vue consolidée pour la direction.</p>
    </section>
    <section class="login-panel">
        <form class="login-card" method="POST" action="{{ route('login.submit') }}">
            @csrf
            <h2>Accès sécurisé</h2>
            <p>Connectez-vous à votre espace KS Gestion.</p>
            @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
            <div class="form-group" style="margin-bottom:14px"><label>Adresse e-mail</label><input type="email" name="email" value="{{ old('email') }}" required autofocus></div>
            <div class="form-group" style="margin-bottom:14px"><label>Mot de passe</label><input type="password" name="password" required></div>
            <label style="display:flex;gap:8px;align-items:center;margin-bottom:20px"><input type="checkbox" name="remember" value="1"> Rester connecté</label>
            <button class="btn btn-primary" style="width:100%;padding:12px" type="submit">Se connecter</button>
            <div class="small muted" style="margin-top:18px;text-align:center">{{ $ksGroup?->address ?? 'Jardin du 02 Octobre à Kaloum' }}</div>
        </form>
    </section>
</div>
</body>
</html>
