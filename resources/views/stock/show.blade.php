@extends('layouts.app')
@section('title',$product->name)
@section('page-title','Fiche de stock')
@section('content')
<div class="toolbar"><a class="btn btn-secondary" href="{{ route('stock.index') }}">← Stock</a><span class="spacer"></span><span class="badge badge-blue">{{ $product->subsidiary->name }}</span></div>
<div class="grid grid-4" style="margin-bottom:18px">
<div class="card metric"><div class="metric-label">Produit</div><div class="metric-value" style="font-size:19px">{{ $product->name }}</div><div class="metric-note">{{ $product->sku }} • {{ $product->unit }}</div></div>
<div class="card metric"><div class="metric-label">Physique</div><div class="metric-value">{{ number_format($product->stockItem->quantity_on_hand,3,',',' ') }}</div></div>
<div class="card metric"><div class="metric-label">Réservé</div><div class="metric-value">{{ number_format($product->stockItem->reserved_quantity,3,',',' ') }}</div></div>
<div class="card metric"><div class="metric-label">Disponible</div><div class="metric-value">{{ number_format($product->availableQuantity(),3,',',' ') }}</div><div class="metric-note">Seuil : {{ number_format($product->low_stock_threshold,3,',',' ') }}</div></div>
</div>
<div class="grid grid-2">
<div class="card"><div class="card-header"><h3>Ajustement de stock</h3></div><div class="card-body">
@if($product->subsidiary->tracks_expiry)<div class="alert alert-warning">Les entrées de cette filiale doivent être enregistrées par lot afin de conserver les dates de production et péremption.</div>@endif
<form method="POST" action="{{ route('stock.adjust',$product) }}">@csrf<div class="form-grid"><div class="form-group"><label>Type</label><select name="type">@if(!$product->subsidiary->tracks_expiry)<option value="adjustment_in">Entrée / correction +</option>@endif<option value="adjustment_out">Sortie / correction -</option></select></div><div class="form-group"><label>Quantité</label><input type="number" step="0.001" name="quantity" required></div><div class="form-group full"><label>Motif obligatoire</label><input name="reason" required placeholder="Ex. inventaire, consommation interne, casse..."></div></div><button class="btn btn-primary" style="margin-top:14px">Enregistrer</button></form>
</div></div>
@if($product->subsidiary->tracks_expiry)
<div class="card"><div class="card-header"><h3>Nouvelle production / lot</h3><span class="badge badge-warning">Péremption +6 mois</span></div><div class="card-body"><form method="POST" action="{{ route('stock.batches.store',$product) }}">@csrf<div class="form-grid"><div class="form-group"><label>N° lot</label><input name="batch_number" required></div><div class="form-group"><label>Date de production</label><input type="date" name="production_date" value="{{ now()->toDateString() }}" required></div><div class="form-group"><label>Quantité produite</label><input type="number" step="0.001" name="quantity" required></div></div><button class="btn btn-primary" style="margin-top:14px">Ajouter le lot</button></form></div></div>
@else
<div class="card"><div class="card-header"><h3>Règle de disponibilité</h3></div><div class="card-body"><p class="muted">Disponible = stock physique − quantités réservées par les commandes validées. Une livraison consomme automatiquement la réservation.</p></div></div>
@endif
</div>
@if($product->subsidiary->tracks_expiry)
<div class="card" style="margin-top:18px"><div class="card-header"><h3>Lots & péremption</h3></div><div class="table-wrap"><table><thead><tr><th>Lot</th><th>Production</th><th>Péremption</th><th class="text-right">Physique</th><th class="text-right">Réservé</th><th>État</th></tr></thead><tbody>@forelse($product->batches as $b)<tr><td class="fw-bold">{{ $b->batch_number }}</td><td>{{ $b->production_date?->format('d/m/Y') }}</td><td>{{ $b->expiry_date?->format('d/m/Y') }}</td><td class="text-right">{{ $b->quantity_on_hand }}</td><td class="text-right">{{ $b->reserved_quantity }}</td><td>@if($b->expiry_date && $b->expiry_date->isPast())<span class="badge badge-danger">Périmé</span>@elseif($b->expiry_date && $b->expiry_date->lte(now()->addDays(30)))<span class="badge badge-warning">À surveiller</span>@else<span class="badge badge-success">Valide</span>@endif</td></tr>@empty<tr><td colspan="6" class="empty">Aucun lot.</td></tr>@endforelse</tbody></table></div></div>
@endif
<div class="card" style="margin-top:18px"><div class="card-header"><h3>Historique des mouvements</h3></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Type</th><th>Lot</th><th class="text-right">Quantité</th><th>Motif</th><th>Utilisateur</th></tr></thead><tbody>@forelse($movements as $m)<tr><td>{{ $m->occurred_at->format('d/m/Y H:i') }}</td><td><span class="badge">{{ $m->type->label() }}</span></td><td>{{ $m->batch?->batch_number }}</td><td class="text-right fw-bold">{{ $m->quantity }}</td><td>{{ $m->reason }}</td><td>{{ $m->creator?->name }}</td></tr>@empty<tr><td colspan="6" class="empty">Aucun mouvement.</td></tr>@endforelse</tbody></table></div><div class="card-body">{{ $movements->links() }}</div></div>
@endsection
