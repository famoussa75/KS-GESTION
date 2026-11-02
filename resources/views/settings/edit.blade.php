@extends('layouts.app')
@section('title','Paramètres')
@section('page-title','Identité légale du groupe')
@section('content')
<div class="grid grid-2">
<div class="card"><div class="card-header"><h3>Informations officielles</h3></div><div class="card-body"><form method="POST" action="{{ route('settings.update') }}">@csrf @method('PUT')
<div class="form-grid">
<div class="form-group full"><label>Dénomination</label><input name="name" value="{{ old('name',$group->name) }}" required></div>
<div class="form-group"><label>RCCM</label><input name="rccm" value="{{ old('rccm',$group->rccm) }}"></div>
<div class="form-group"><label>NIF</label><input name="nif" value="{{ old('nif',$group->nif) }}"></div>
<div class="form-group full"><label>RIB</label><input name="rib" value="{{ old('rib',$group->rib) }}"></div>
<div class="form-group full"><label>Adresse</label><input name="address" value="{{ old('address',$group->address) }}"></div>
<div class="form-group"><label>Téléphone</label><input name="phone" value="{{ old('phone',$group->phone) }}"></div>
<div class="form-group"><label>E-mail</label><input type="email" name="email" value="{{ old('email',$group->email) }}"></div>
</div><button class="btn btn-primary" style="margin-top:18px">Enregistrer</button></form></div></div>
<div class="card"><div class="card-header"><h3>Identité visuelle</h3></div><div class="card-body"><img src="{{ asset('images/ks-logo.png') }}" style="max-width:320px;width:100%;background:#fff;border:1px solid var(--ks-border);border-radius:18px;padding:18px"><p class="muted" style="line-height:1.7">Palette intégrée au template : bleu <strong>#2177B3</strong>, bleu profond <strong>#1C578D</strong> et graphite <strong>#404143</strong>.</p></div></div>
</div>
@endsection
