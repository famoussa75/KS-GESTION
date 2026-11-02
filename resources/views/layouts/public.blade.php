<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Document KS')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/ks-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div style="padding:24px" class="no-print"><div style="max-width:950px;margin:0 auto;display:flex;justify-content:flex-end"><button class="btn btn-primary" data-print>Imprimer / PDF</button></div></div>
<div class="doc-shell">@yield('content')</div>
<script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
