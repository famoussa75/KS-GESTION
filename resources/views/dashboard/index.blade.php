@extends('layouts.app')
@section('title','Tableau de bord')
@section('page-title','Tableau de bord groupe')
@section('content')
<div class="hero"><h2>Vue consolidée KS Groupe</h2><p>Suivi en temps réel des ventes, encaissements, stocks, commandes et alertes des trois filiales.</p></div>
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="card metric"><div class="metric-accent"></div><div class="metric-label">Facturation</div><div class="metric-value">{{ number_format($invoiceTotal,0,',',' ') }} GNF</div><div class="metric-note">Montant cumulé des factures définitives</div></div>
    <div class="card metric"><div class="metric-accent"></div><div class="metric-label">À recouvrer</div><div class="metric-value">{{ number_format($outstandingTotal,0,',',' ') }} GNF</div><div class="metric-note">Solde non encaissé</div></div>
    <div class="card metric"><div class="metric-accent"></div><div class="metric-label">Stock physique</div><div class="metric-value">{{ number_format($stockUnits,3,',',' ') }}</div><div class="metric-note">Unités cumulées • {{ $productCount }} produit(s)</div></div>
    <div class="card metric"><div class="metric-accent"></div><div class="metric-label">Commandes actives</div><div class="metric-value">{{ $pendingOrders }}</div><div class="metric-note">{{ $openCash }} caisse(s) actuellement ouverte(s)</div></div>
</div>
<div class="grid grid-2">
    <div class="card"><div class="card-header"><h3>Alertes opérationnelles</h3><a href="{{ route('stock.index') }}" class="small">Voir le stock</a></div><div class="card-body">
        @forelse($alerts as $alert)<div class="alert {{ $alert->type->value === 'low_stock' ? 'alert-warning' : 'alert-danger' }}" style="margin-bottom:10px"><strong>{{ $alert->title }}</strong><div class="small" style="margin-top:3px">{{ $alert->message }}</div></div>@empty<div class="empty">Aucune alerte active.</div>@endforelse
    </div></div>
    <div class="card"><div class="card-header"><h3>Dernières factures</h3><a href="{{ route('invoices.index') }}" class="small">Toutes les factures</a></div><div class="table-wrap"><table><thead><tr><th>N°</th><th>Filiale</th><th>Client</th><th class="text-right">Total</th></tr></thead><tbody>
        @forelse($recentInvoices as $invoice)<tr><td><a class="fw-bold" href="{{ route('invoices.show',$invoice) }}">{{ $invoice->number }}</a></td><td>{{ $invoice->issuer_name }}</td><td>{{ $invoice->client_name }}</td><td class="text-right fw-bold">{{ number_format($invoice->total,0,',',' ') }}</td></tr>@empty<tr><td colspan="4" class="empty">Aucune facture.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
