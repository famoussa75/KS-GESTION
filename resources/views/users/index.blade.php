@extends('layouts.app')
@section('title','Utilisateurs')
@section('page-title','Utilisateurs & droits')
@section('content')
<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h3>Créer un utilisateur</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group"><label>Nom complet</label><input name="name" required></div>
                    <div class="form-group"><label>E-mail</label><input type="email" name="email" required></div>
                    <div class="form-group"><label>Mot de passe</label><input type="password" name="password" required><span class="help">10 caractères minimum</span></div>
                    <div class="form-group"><label>Rôle</label><select name="role">@foreach($roles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</select></div>
                    <div class="form-group full"><label>Filiales autorisées</label><div class="checkbox-row">@foreach($subsidiaries as $s)<label><input type="checkbox" name="subsidiaries[]" value="{{ $s->id }}" checked> {{ $s->name }}</label>@endforeach</div></div>
                </div>
                <button class="btn btn-primary" style="margin-top:16px">Créer</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Règles d’accès</h3></div>
        <div class="card-body">
            <div class="stat-line"><strong>Accès total</strong><span>Pilotage, ventes, caisse, stock, commandes, paramètres et SYSCOHADA</span></div>
            <div class="stat-line"><strong>Caissier</strong><span>Caisse uniquement</span></div>
            <div class="stat-line"><strong>Magasinier</strong><span>Stock uniquement</span></div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <div class="card-header"><h3>Comptes existants</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Utilisateur</th><th>Rôle</th><th>Filiales</th><th>État</th><th></th></tr></thead>
            <tbody>
            @foreach($users as $user)
                @php($formId = 'user-form-'.$user->id)
                <tr>
                    <td>
                        <form id="{{ $formId }}" method="POST" action="{{ route('users.update',$user) }}">@csrf @method('PUT')</form>
                        <input form="{{ $formId }}" name="name" value="{{ $user->name }}">
                        <input form="{{ $formId }}" type="email" name="email" value="{{ $user->email }}" style="margin-top:5px">
                    </td>
                    <td>
                        <select form="{{ $formId }}" name="role">@foreach($roles as $role)<option value="{{ $role->value }}" {{ $user->role === $role ? 'selected':'' }}>{{ $role->label() }}</option>@endforeach</select>
                        <input form="{{ $formId }}" type="password" name="password" placeholder="Nouveau mot de passe" style="margin-top:5px">
                    </td>
                    <td><div class="checkbox-row">@foreach($subsidiaries as $s)<label><input form="{{ $formId }}" type="checkbox" name="subsidiaries[]" value="{{ $s->id }}" {{ $user->subsidiaries->contains($s->id)?'checked':'' }}> {{ $s->code }}</label>@endforeach</div></td>
                    <td><label><input form="{{ $formId }}" type="checkbox" name="active" value="1" {{ $user->active?'checked':'' }}> Actif</label></td>
                    <td><button form="{{ $formId }}" class="btn btn-secondary btn-sm">Enregistrer</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
