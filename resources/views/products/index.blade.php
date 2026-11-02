@extends('layouts.app')
@section('title','Produits')
@section('page-title','Catalogue produits & services')
@section('content')
<div class="grid grid-3" style="grid-template-columns:1fr 2fr">
<div class="card"><div class="card-header"><h3>Nouveau produit / service</h3></div><div class="card-body"><form method="POST" action="{{ route('products.store') }}">@csrf
<div class="form-group"><label>Filiale</label><select name="subsidiary_id" required><option value="">— Choisir —</option>@foreach($subsidiaries as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
<div class="form-grid" style="margin-top:12px"><div class="form-group"><label>SKU / Référence</label><input name="sku" required></div><div class="form-group"><label>Unité</label><input name="unit" value="unité" required></div></div>
<div class="form-group" style="margin-top:12px"><label>Nom</label><input name="name" required></div><div class="form-group" style="margin-top:12px"><label>Description</label><textarea name="description"></textarea></div>
<div class="form-grid"><div class="form-group"><label>Prix de vente</label><input type="number" step="0.01" name="sale_price" value="0" required></div><div class="form-group"><label>Coût indicatif</label><input type="number" step="0.01" name="cost_price" value="0" required></div><div class="form-group"><label>Taxe (%)</label><input type="number" step="0.01" name="tax_rate" value="0" required></div><div class="form-group"><label>Seuil d’alerte</label><input type="number" step="0.001" name="low_stock_threshold" value="0" required></div></div>
<div class="form-group" style="margin-top:12px"><label>Compte de produit SYSCOHADA</label><select name="revenue_account_id"><option value="">Automatique selon filiale</option>@foreach($revenueAccounts as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->label }}</option>@endforeach</select></div>
<button class="btn btn-primary" style="margin-top:16px">Créer</button></form></div></div>
<div class="card"><div class="card-header"><h3>Catalogue</h3><form style="display:flex;gap:8px"><select name="subsidiary_id"><option value="">Toutes les filiales</option>@foreach($subsidiaries as $s)<option value="{{ $s->id }}" {{ request('subsidiary_id')==$s->id?'selected':'' }}>{{ $s->name }}</option>@endforeach</select><input name="q" value="{{ request('q') }}" placeholder="Produit / SKU"><button class="btn btn-secondary btn-sm">Filtrer</button></form></div><div class="table-wrap"><table><thead><tr><th>Produit</th><th>Filiale</th><th class="text-right">Prix</th><th class="text-right">Stock</th><th>Seuil</th></tr></thead><tbody>
@forelse($products as $p)<tr><td><a class="fw-bold" href="{{ route('stock.show',$p) }}">{{ $p->name }}</a><div class="small muted">{{ $p->sku }} • {{ $p->unit }}</div></td><td>{{ $p->subsidiary->name }}</td><td class="text-right">{{ number_format($p->sale_price,0,',',' ') }}</td><td class="text-right fw-bold">{{ number_format($p->stockItem?->quantity_on_hand ?? 0,3,',',' ') }}</td><td>{{ number_format($p->low_stock_threshold,3,',',' ') }}</td></tr>@empty<tr><td colspan="5" class="empty">Aucun produit.</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $products->links() }}</div></div>
</div>
@endsection
