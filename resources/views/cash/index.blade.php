@extends('layouts.app')
@section('title','Caisse')
@section('page-title','Gestion de caisse')
@section('content')
<div class="hero"><h2>Sessions de caisse contrôlées</h2><p>Chaque ouverture commence par un fond saisi manuellement. À la clôture, le système calcule le solde théorique et l’écart avec le comptage réel.</p></div>
<div class="grid grid-3" style="margin-bottom:18px">
@foreach($registers as $register) @php($open=$register->sessions->firstWhere('closed_at',null))
<div class="card"><div class="card-header"><h3>{{ $register->name }}</h3><span class="badge badge-blue">{{ $register->subsidiary->code }}</span></div><div class="card-body">
@if($open)<div class="alert alert-success"><strong>Caisse ouverte</strong><div class="small">Depuis {{ $open->opened_at->format('d/m/Y H:i') }}</div></div><a class="btn btn-primary" href="{{ route('cash.show',$open) }}">Ouvrir la session</a>
@else<form method="POST" action="{{ route('cash.open') }}">@csrf<input type="hidden" name="cash_register_id" value="{{ $register->id }}"><div class="form-group"><label>Fond de départ</label><input type="number" step="0.01" min="0" name="opening_float" value="0" required></div><button class="btn btn-primary" style="margin-top:12px">Ouvrir la caisse</button></form>@endif
</div></div>@endforeach
</div>
<div class="card"><div class="card-header"><h3>Historique des sessions</h3></div><div class="table-wrap"><table><thead><tr><th>Session</th><th>Filiale</th><th>Ouverture</th><th>Clôture</th><th class="text-right">Fond</th><th class="text-right">Théorique</th><th class="text-right">Réel</th><th class="text-right">Écart</th></tr></thead><tbody>
@forelse($recentSessions as $s)<tr><td><a class="fw-bold" href="{{ route('cash.show',$s) }}">{{ $s->number }}</a></td><td>{{ $s->register->subsidiary->name }}</td><td>{{ $s->opened_at->format('d/m/Y H:i') }}</td><td>{{ $s->closed_at?->format('d/m/Y H:i') ?? 'Ouverte' }}</td><td class="text-right">{{ number_format($s->opening_float,0,',',' ') }}</td><td class="text-right">{{ $s->theoretical_closing!==null?number_format($s->theoretical_closing,0,',',' '):'—' }}</td><td class="text-right">{{ $s->actual_closing!==null?number_format($s->actual_closing,0,',',' '):'—' }}</td><td class="text-right">@if($s->variance!==null)<span class="badge {{ abs((float)$s->variance)<0.01?'badge-success':'badge-danger' }}">{{ number_format($s->variance,0,',',' ') }}</span>@else—@endif</td></tr>@empty<tr><td colspan="8" class="empty">Aucune session.</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $recentSessions->links() }}</div></div>
@endsection
