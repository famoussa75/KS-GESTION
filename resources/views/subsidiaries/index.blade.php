@extends('layouts.app')
@section('title','Filiales')
@section('page-title','Filiales')
@section('content')
<div class="hero"><h2>Structure du groupe</h2><p>Chaque document est émis au nom de sa filiale. Un logo spécifique peut être chargé ; à défaut, le logo du groupe est utilisé.</p></div>
<div class="toolbar" style="justify-content:space-between"><a class="btn btn-primary" href="{{ route('subsidiaries.create') }}">+ Nouvelle filiale</a><span class="small muted">Raison sociale, RCCM, RIB du groupe ? Voir <a href="{{ route('settings.edit') }}">Paramètres</a>.</span></div>
<div class="grid grid-3">
@foreach($subsidiaries as $subsidiary)
<div class="card"><div class="card-body">
    <div style="display:flex;justify-content:space-between;gap:15px;align-items:flex-start">
        <img class="logo-preview" src="{{ $subsidiary->effectiveLogoUrl() }}" alt="{{ $subsidiary->name }}">
        <span class="badge badge-blue">{{ $subsidiary->code }}</span>
    </div>
    <h3 style="margin:18px 0 5px">{{ $subsidiary->name }}</h3><div class="muted">{{ $subsidiary->activity }}</div>
    <div style="margin-top:14px"><span class="badge {{ $subsidiary->tracks_expiry ? 'badge-warning' : '' }}">{{ $subsidiary->tracks_expiry ? 'Lots & péremption' : 'Stock standard' }}</span></div>
</div><div class="card-header"><span class="small muted">{{ $subsidiary->active ? 'Active' : 'Inactive' }}</span><a class="btn btn-secondary btn-sm" href="{{ route('subsidiaries.edit',$subsidiary) }}">Configurer</a></div></div>
@endforeach
</div>
@endsection
