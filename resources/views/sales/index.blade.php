@extends('layouts.app')
@section('title','Ventes')
@section('page-title','Ventes')
@section('content')
<div class="toolbar">
    <a class="btn btn-primary" href="{{ route('sales.create') }}">+ Nouvelle vente</a>
    <span class="spacer"></span>
    <form style="display:flex;gap:8px;flex-wrap:wrap">
        @if(auth()->user()->isAdmin())
        <select name="intent">
            <option value="">Tous types</option>
            <option value="quote" {{ request('intent')=='quote'?'selected':'' }}>Devis</option>
            <option value="order" {{ request('intent')=='order'?'selected':'' }}>Commandes</option>
            <option value="direct" {{ request('intent')=='direct'?'selected':'' }}>Ventes directes</option>
        </select>
        @elseif(auth()->user()->isCashier())
        <select name="intent">
            <option value="">Tous types</option>
            <option value="order" {{ request('intent')=='order'?'selected':'' }}>Commandes</option>
            <option value="direct" {{ request('intent')=='direct'?'selected':'' }}>Ventes directes</option>
        </select>
        @endif
        <select name="status"><option value="">Tous statuts</option>@foreach(\App\Enums\SaleStatus::cases() as $s)<option value="{{ $s->value }}" {{ request('status')==$s->value?'selected':'' }}>{{ $s->label() }}</option>@endforeach</select>
        <select name="subsidiary_id"><option value="">Toutes filiales</option>@foreach($subsidiaries as $s)<option value="{{ $s->id }}" {{ request('subsidiary_id')==$s->id?'selected':'' }}>{{ $s->name }}</option>@endforeach</select>
        <input name="q" value="{{ request('q') }}" placeholder="N° ou client">
        <button class="btn btn-secondary">Filtrer</button>
    </form>
</div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Numéro</th><th>Type</th><th>Date</th><th>Filiale</th><th>Client</th><th>Statut</th><th class="text-right">Total</th><th></th></tr></thead><tbody>
@forelse($sales as $s)
<tr>
    <td><a class="fw-bold" href="{{ route('sales.show',$s) }}">{{ $s->number }}</a></td>
    <td><span class="badge badge-blue">{{ $s->intent->label() }}</span></td>
    <td>{{ $s->issue_date->format('d/m/Y') }}</td>
    <td>{{ $s->subsidiary->name }}</td>
    <td>{{ $s->client->name ?? ($s->walk_in_client_name ?: ($s->intent->value === 'direct' ? 'Client comptoir' : '—')) }}</td>
    <td><span class="badge {{ $s->status->value==='invoiced'?'badge-success':($s->status->value==='draft'?'badge-warning':'badge-blue') }}">{{ $s->status->label() }}</span></td>
    <td class="text-right fw-bold">{{ number_format($s->total,0,',',' ') }} GNF</td>
    <td>@if($s->invoice)<a class="btn btn-secondary btn-sm" href="{{ route('invoices.show',$s->invoice) }}">Facture</a>@endif</td>
</tr>
@empty<tr><td colspan="8" class="empty">Aucune vente.</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $sales->links() }}</div></div>
@endsection
