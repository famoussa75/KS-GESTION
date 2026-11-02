@extends('layouts.app')
@section('title','Nouvelle filiale')
@section('page-title','Créer une filiale')
@section('content')
<div class="toolbar"><a class="btn btn-secondary" href="{{ route('subsidiaries.index') }}">← Retour</a></div>
<div class="card" style="max-width:850px"><div class="card-header"><h3>Nouvelle filiale</h3></div><div class="card-body">
<form method="POST" action="{{ route('subsidiaries.store') }}">@csrf
<div class="form-grid">
<div class="form-group"><label>Nom de la filiale</label><input name="name" value="{{ old('name') }}" required></div>
<div class="form-group"><label>Code (préfixe des documents)</label><input name="code" value="{{ old('code') }}" maxlength="12" placeholder="ex : KSN" required><span class="help">Utilisé dans la numérotation des factures/commandes, ex. FAC-{{ old('code','XXX') }}-2026-000001.</span></div>
<div class="form-group full"><label>Activité</label><input name="activity" value="{{ old('activity') }}" required></div>
<div class="form-group full"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="tracks_expiry" value="1" {{ old('tracks_expiry') ? 'checked' : '' }}> Suivre les lots, dates de production et péremption</label></div>
<div class="form-group"><label>Durée de conservation (mois)</label><input type="number" name="default_shelf_life_months" min="1" max="60" value="{{ old('default_shelf_life_months') }}"></div>
</div><div style="margin-top:18px"><button class="btn btn-primary">Créer la filiale</button></div>
</form></div></div>
@endsection
