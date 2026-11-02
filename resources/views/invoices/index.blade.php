@extends('layouts.app')
@section('title','Factures')
@section('page-title','Factures définitives')
@section('content')
<div class="hero"><h2>Registre des factures définitives</h2><p>Numérotation automatique et séquentielle par filiale et par année. Les factures définitives sont techniquement immuables et non supprimables.</p></div>
<div class="toolbar"><span class="badge badge-success">Immutabilité activée</span><span class="spacer"></span><form style="display:flex;gap:8px"><select name="subsidiary_id"><option value="">Toutes filiales</option>@foreach($subsidiaries as $s)<option value="{{ $s->id }}" {{ request('subsidiary_id')==$s->id?'selected':'' }}>{{ $s->name }}</option>@endforeach</select><input name="q" value="{{ request('q') }}" placeholder="N° ou client"><button class="btn btn-secondary">Filtrer</button></form></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Numéro</th><th>Date</th><th>Filiale</th><th>Client</th><th class="text-right">Total</th><th class="text-right">Payé</th><th class="text-right">Solde</th></tr></thead><tbody>
@forelse($invoices as $invoice) @php($paid=(float)$invoice->payments->sum('amount'))<tr><td><a class="fw-bold" href="{{ route('invoices.show',$invoice) }}">{{ $invoice->number }}</a></td><td>{{ $invoice->issue_date->format('d/m/Y') }}</td><td>{{ $invoice->issuer_name }}</td><td>{{ $invoice->client_name }}</td><td class="text-right fw-bold">{{ number_format($invoice->total,0,',',' ') }}</td><td class="text-right">{{ number_format($paid,0,',',' ') }}</td><td class="text-right"><span class="badge {{ ((float)$invoice->total-$paid)<=0.01?'badge-success':'badge-warning' }}">{{ number_format(max(0,(float)$invoice->total-$paid),0,',',' ') }}</span></td></tr>@empty<tr><td colspan="7" class="empty">Aucune facture définitive.</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $invoices->links() }}</div></div>
@endsection
