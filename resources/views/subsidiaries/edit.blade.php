@extends('layouts.app')
@section('title','Modifier '.$subsidiary->name)
@section('page-title','Configuration filiale')
@section('content')
<div class="toolbar"><a class="btn btn-secondary" href="{{ route('subsidiaries.index') }}">← Retour</a></div>
<div class="card" style="max-width:850px"><div class="card-header"><h3>{{ $subsidiary->name }}</h3><span class="badge badge-blue">{{ $subsidiary->code }}</span></div><div class="card-body">
<form method="POST" action="{{ route('subsidiaries.update',$subsidiary) }}" enctype="multipart/form-data">@csrf @method('PUT')
<div class="form-grid">
<div class="form-group"><label>Nom de la filiale</label><input name="name" value="{{ old('name',$subsidiary->name) }}" required></div>
<div class="form-group"><label>Activité</label><input name="activity" value="{{ old('activity',$subsidiary->activity) }}" required></div>
<div class="form-group"><label>Logo spécifique</label><input type="file" name="logo" accept="image/*"><span class="help">PNG/JPG, 2 Mo max. À défaut, le logo KS Groupe est affiché.</span></div>
<div class="form-group"><label>Aperçu</label><img class="logo-preview" src="{{ $subsidiary->effectiveLogoUrl() }}"></div>
<div class="form-group full"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="tracks_expiry" value="1" {{ $subsidiary->tracks_expiry ? 'checked' : '' }}> Suivre les lots, dates de production et péremption</label></div>
<div class="form-group"><label>Durée de conservation (mois)</label><input type="number" name="default_shelf_life_months" min="1" max="60" value="{{ old('default_shelf_life_months',$subsidiary->default_shelf_life_months) }}"><span class="help">Jus Sossoguinai : 6 mois.</span></div>
</div><div style="margin-top:18px"><button class="btn btn-primary">Enregistrer</button></div>
</form></div></div>
@endsection
