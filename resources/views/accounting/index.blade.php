@extends('layouts.app')
@section('title','SYSCOHADA')
@section('page-title','Pré-comptabilité SYSCOHADA')
@section('content')
<div class="hero"><h2>Journalisation automatique</h2><p>Les factures définitives et mouvements de caisse génèrent des écritures équilibrées. Le plan fourni est une base de paramétrage SYSCOHADA à faire valider par l’expert-comptable avant production.</p></div>
<div class="toolbar"><form style="display:flex;gap:8px;flex-wrap:wrap"><select name="subsidiary_id"><option value="">Toutes filiales</option>@foreach($subsidiaries as $s)<option value="{{ $s->id }}" {{ request('subsidiary_id')==$s->id?'selected':'' }}>{{ $s->name }}</option>@endforeach</select><select name="journal_id"><option value="">Tous journaux</option>@foreach($journals as $j)<option value="{{ $j->id }}" {{ request('journal_id')==$j->id?'selected':'' }}>{{ $j->code }} — {{ $j->label }}</option>@endforeach</select><input type="date" name="from" value="{{ request('from') }}"><input type="date" name="to" value="{{ request('to') }}"><button class="btn btn-secondary">Filtrer</button></form></div>
<div class="grid grid-3" style="grid-template-columns:2fr 1fr">
<div class="card"><div class="card-header"><h3>Écritures comptables</h3></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Journal</th><th>Référence</th><th>Libellé</th><th class="text-right">Débit</th><th class="text-right">Crédit</th></tr></thead><tbody>
@forelse($entries as $e)
@php
    $d = (float) $e->lines->sum('debit');
    $c = (float) $e->lines->sum('credit');
@endphp
<tr><td>{{ $e->entry_date->format('d/m/Y') }}</td><td><span class="badge badge-blue">{{ $e->journal->code }}</span></td><td class="fw-bold">{{ $e->reference }}</td><td>{{ $e->label }}<div class="small muted">{{ $e->subsidiary->name }}</div></td><td class="text-right">{{ number_format($d,0,',',' ') }}</td><td class="text-right">{{ number_format($c,0,',',' ') }}</td></tr><tr><td></td><td colspan="5" style="padding-top:0"><div class="small muted">@foreach($e->lines as $line)<span style="display:inline-block;margin-right:14px">{{ $line->account->code }} {{ $line->account->label }} : D {{ number_format($line->debit,0,',',' ') }} / C {{ number_format($line->credit,0,',',' ') }}</span>@endforeach</div></td></tr>@empty<tr><td colspan="6" class="empty">Aucune écriture.</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $entries->links() }}</div></div>
<div class="card"><div class="card-header"><h3>Plan de comptes initial</h3></div><div class="table-wrap"><table><thead><tr><th>Compte</th><th>Intitulé</th></tr></thead><tbody>@foreach($accounts as $a)<tr><td class="fw-bold">{{ $a->code }}</td><td>{{ $a->label }}</td></tr>@endforeach</tbody></table></div></div>
</div>
@endsection
