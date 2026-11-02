@extends('layouts.app')
@section('title','Stock')
@section('page-title','Gestion des stocks')
@section('content')
<div class="hero"><h2>{{ $isConsolidated ? 'Stock consolidé du groupe' : 'Stocks par filiale' }}</h2><p>Quantités physiques, réservations automatiques des commandes et alertes de seuil. Pour Jus Sossoguinai, les lots suivent la production et la péremption à 6 mois.</p></div>
<div class="toolbar"><form style="display:flex;gap:8px"><select name="subsidiary_id"><option value="">Toutes les filiales</option>@foreach($subsidiaries as $s)<option value="{{ $s->id }}" {{ request('subsidiary_id')==$s->id?'selected':'' }}>{{ $s->name }}</option>@endforeach</select><button class="btn btn-secondary">Afficher</button></form></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Produit</th><th>Filiale</th><th class="text-right">Physique</th><th class="text-right">Réservé</th><th class="text-right">Disponible</th><th>Seuil</th><th>État</th></tr></thead><tbody>
@forelse($products as $p) @php($available=(float)($p->stockItem?->quantity_on_hand??0)-(float)($p->stockItem?->reserved_quantity??0))
<tr><td><a class="fw-bold" href="{{ route('stock.show',$p) }}">{{ $p->name }}</a><div class="small muted">{{ $p->sku }} • {{ $p->unit }}</div></td><td>{{ $p->subsidiary->name }}</td><td class="text-right">{{ number_format($p->stockItem?->quantity_on_hand??0,3,',',' ') }}</td><td class="text-right">{{ number_format($p->stockItem?->reserved_quantity??0,3,',',' ') }}</td><td class="text-right fw-bold">{{ number_format($available,3,',',' ') }}</td><td>{{ number_format($p->low_stock_threshold,3,',',' ') }}</td><td>@if($available <= (float)$p->low_stock_threshold)<span class="badge badge-danger">Stock faible</span>@else<span class="badge badge-success">Normal</span>@endif @if($p->subsidiary->tracks_expiry)<span class="badge badge-warning">Lots</span>@endif</td></tr>
@empty<tr><td colspan="7" class="empty">Aucun produit en stock.</td></tr>@endforelse</tbody></table></div><div class="card-body">{{ $products->links() }}</div></div>
@endsection
