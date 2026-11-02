@extends('layouts.app')
@section('title','Alertes')
@section('page-title','Centre d’alertes')
@section('content')
<div class="hero"><h2>Alertes actives</h2><p>Stock faible et péremption proche, tous rôles concernés confondus.</p></div>
<div class="toolbar"><form style="display:flex;gap:8px"><select name="type"><option value="">Tous les types</option><option value="low_stock" {{ request('type')=='low_stock'?'selected':'' }}>Stock faible</option><option value="expiry" {{ request('type')=='expiry'?'selected':'' }}>Péremption</option></select><button class="btn btn-secondary">Filtrer</button></form></div>
<div class="card"><div class="card-body">
@forelse($alerts as $alert)
<div class="alert {{ $alert->type->value === 'low_stock' ? 'alert-warning' : 'alert-danger' }}" style="margin-bottom:10px">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
        <div><strong>{{ $alert->title }}</strong><div class="small" style="margin-top:3px">{{ $alert->message }}</div></div>
        <span class="badge badge-blue">{{ $alert->type->label() }}</span>
    </div>
    <div class="small muted" style="margin-top:6px">{{ $alert->subsidiary?->name }}</div>
</div>
@empty
<div class="empty">Aucune alerte active. Tout est sous contrôle.</div>
@endforelse
</div><div class="card-body">{{ $alerts->links() }}</div></div>
@endsection
