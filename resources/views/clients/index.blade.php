@extends('layouts.app')
@section('title','Clients')
@section('page-title','Clients')
@section('content')
<div class="grid grid-3" style="grid-template-columns:1fr 2fr">
<div class="card"><div class="card-header"><h3>Nouveau client</h3></div><div class="card-body"><form method="POST" action="{{ route('clients.store') }}">@csrf
<div class="form-group"><label>Filiale</label><select name="subsidiary_id"><option value="">Client groupe / partagé</option>@foreach($subsidiaries as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
<div class="form-group" style="margin-top:10px"><label>Nom / contact</label><input name="name" required></div><div class="form-group" style="margin-top:10px"><label>Société</label><input name="company"></div><div class="form-group" style="margin-top:10px"><label>E-mail</label><input type="email" name="email"></div><div class="form-group" style="margin-top:10px"><label>Téléphone</label><input name="phone"></div><div class="form-group" style="margin-top:10px"><label>NIF</label><input name="nif"></div><div class="form-group" style="margin-top:10px"><label>Adresse</label><textarea name="address"></textarea></div>
<button class="btn btn-primary" style="margin-top:14px">Ajouter</button></form></div></div>
<div class="card"><div class="card-header"><h3>Répertoire clients</h3><form><input name="q" value="{{ request('q') }}" placeholder="Rechercher..." style="width:220px"></form></div><div class="table-wrap"><table><thead><tr><th>Client</th><th>Filiale</th><th>Contact</th><th>Adresse</th></tr></thead><tbody>@forelse($clients as $client)<tr><td><strong>{{ $client->name }}</strong><div class="small muted">{{ $client->company }}</div></td><td>{{ $client->subsidiary?->name ?? 'Groupe' }}</td><td>{{ $client->phone }}<div class="small">{{ $client->email }}</div></td><td class="small">{{ $client->address }}</td></tr>@empty<tr><td colspan="4" class="empty">Aucun client.</td></tr>@endforelse</tbody></table></div><div class="card-body">{{ $clients->links() }}</div></div>
</div>
@endsection
