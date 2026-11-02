@extends('layouts.app')
@section('title',$editing ? 'Modifier vente' : 'Nouvelle vente')
@section('page-title',$editing ? 'Modifier la vente' : 'Nouvelle vente')
@section('content')
@php
$initialLines = old('lines', $editing ? $sale->lines->map(fn($l)=>['product_id'=>$l->product_id,'description'=>$l->description,'quantity'=>$l->quantity,'unit_price'=>$l->unit_price,'tax_rate'=>$l->tax_rate])->toArray() : [['product_id'=>'','description'=>'','quantity'=>1,'unit_price'=>0,'tax_rate'=>0]]);
$currentIntent = old('intent', $defaultIntent);
@endphp
<form method="POST" action="{{ $editing ? route('sales.update',$sale) : route('sales.store') }}">@csrf @if($editing) @method('PUT') @endif

@if(count($allowedIntents) > 1)
<div class="card" style="margin-bottom:18px"><div class="card-body">
<div class="form-group"><label>Type de vente</label>
<div style="display:flex;gap:10px;flex-wrap:wrap" id="intent-selector">
@foreach($allowedIntents as $intent)
<label style="display:flex;align-items:center;gap:6px;border:1px solid var(--ks-border);border-radius:10px;padding:9px 14px;cursor:pointer">
<input type="radio" name="intent" value="{{ $intent->value }}" {{ $currentIntent===$intent->value?'checked':'' }} data-intent-radio>
{{ $intent->label() }}
</label>
@endforeach
</div>
</div>
</div></div>
@else
<input type="hidden" name="intent" value="{{ $allowedIntents[0]->value }}">
@endif

<div class="grid grid-3" style="grid-template-columns:2fr 1fr;margin-bottom:18px">
<div class="card"><div class="card-header"><h3>Émetteur & client</h3></div><div class="card-body"><div class="form-grid">
@php($lockSubsidiary = $editing || $subsidiaries->count() === 1)
<div class="form-group"><label>Filiale émettrice</label><select name="subsidiary_id" id="doc-subsidiary" required {{ $lockSubsidiary?'disabled':'' }}><option value="">— Choisir —</option>@foreach($subsidiaries as $s)<option value="{{ $s->id }}" {{ old('subsidiary_id',$sale->subsidiary_id)==$s->id?'selected':'' }}>{{ $s->name }} — {{ $s->activity }}</option>@endforeach</select>@if($lockSubsidiary)<input type="hidden" name="subsidiary_id" value="{{ old('subsidiary_id',$sale->subsidiary_id) }}">@endif</div>
<div class="form-group"><label>Client</label><select name="client_id"><option value="">— Choisir —</option>@foreach($clients as $c)<option value="{{ $c->id }}" {{ old('client_id',$sale->client_id)==$c->id?'selected':'' }}>{{ $c->name }}{{ $c->company?' — '.$c->company:'' }}</option>@endforeach</select></div>
<div class="form-group" data-intent-field="direct"><label>Nom du client comptoir (si aucun client sélectionné)</label><input type="text" name="walk_in_client_name" value="{{ old('walk_in_client_name') }}" placeholder="Client comptoir"></div>
<div class="form-group"><label>Date d’émission</label><input type="date" name="issue_date" value="{{ old('issue_date', optional($sale->issue_date)->format('Y-m-d') ?? now()->toDateString()) }}" required></div>
<div class="form-group" data-intent-field="quote"><label>Validité</label><input type="date" name="valid_until" value="{{ old('valid_until', optional($sale->valid_until)->format('Y-m-d')) }}"></div>
<div class="form-group" data-intent-field="order"><label>Livraison souhaitée</label><input type="date" name="requested_delivery_date" value="{{ old('requested_delivery_date', optional($sale->requested_delivery_date)->format('Y-m-d')) }}"></div>
</div></div></div>
<div class="card"><div class="card-header"><h3>Totaux</h3></div><div class="card-body"><div class="stat-line"><span>Sous-total HT</span><strong data-subtotal>0 GNF</strong></div><div class="stat-line"><span>Taxes</span><strong data-tax-total>0 GNF</strong></div><div class="stat-line" style="font-size:17px;color:var(--ks-blue-deep)"><span>Total TTC</span><strong data-grand-total>0 GNF</strong></div></div></div>
</div>

<div class="card"><div class="card-header"><h3>Lignes</h3><button class="btn btn-secondary btn-sm" type="button" data-add-line data-target="#sale-lines">+ Ajouter une ligne</button></div><div class="table-wrap"><table class="line-items"><thead><tr><th style="min-width:210px">Produit / service</th><th style="min-width:220px">Description</th><th style="width:110px">Qté</th><th style="width:150px">Prix unitaire</th><th style="width:100px">Taxe %</th><th class="text-right" style="width:150px">Total</th><th></th></tr></thead><tbody id="sale-lines">
@foreach($initialLines as $i=>$line)<tr data-line-row><td><select name="lines[{{ $i }}][product_id]" data-product-select><option value="">Ligne libre / prestation</option>@foreach($products as $product)<option value="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->sale_price }}" data-tax="{{ $product->tax_rate }}" data-subsidiary="{{ $product->subsidiary_id }}" {{ ($line['product_id']??null)==$product->id?'selected':'' }}>{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select></td><td><input name="lines[{{ $i }}][description]" value="{{ $line['description']??'' }}" data-description></td><td><input type="number" step="0.001" min="0.001" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity']??1 }}" data-quantity required></td><td><input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_price]" value="{{ $line['unit_price']??0 }}" data-unit-price required></td><td><input type="number" step="0.01" min="0" max="100" name="lines[{{ $i }}][tax_rate]" value="{{ $line['tax_rate']??0 }}" data-tax-rate required></td><td class="text-right fw-bold" data-line-total>0</td><td><button type="button" class="remove-line" data-remove-line>×</button></td></tr>@endforeach
</tbody></table></div></div>

<div class="card" style="margin-top:18px"><div class="card-body"><div class="form-group"><label>Notes / conditions</label><textarea name="notes">{{ old('notes',$sale->notes) }}</textarea></div><div class="toolbar" style="margin:16px 0 0"><button class="btn btn-primary">{{ $editing ? 'Enregistrer les modifications' : 'Créer la vente' }}</button><a class="btn btn-secondary" href="{{ route('sales.index') }}">Annuler</a></div></div></div>
</form>
<template data-line-template><tr data-line-row><td><select name="lines[__INDEX__][product_id]" data-product-select><option value="">Ligne libre / prestation</option>@foreach($products as $product)<option value="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->sale_price }}" data-tax="{{ $product->tax_rate }}" data-subsidiary="{{ $product->subsidiary_id }}">{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select></td><td><input name="lines[__INDEX__][description]" data-description></td><td><input type="number" step="0.001" min="0.001" name="lines[__INDEX__][quantity]" value="1" data-quantity required></td><td><input type="number" step="0.01" min="0" name="lines[__INDEX__][unit_price]" value="0" data-unit-price required></td><td><input type="number" step="0.01" min="0" max="100" name="lines[__INDEX__][tax_rate]" value="0" data-tax-rate required></td><td class="text-right fw-bold" data-line-total>0</td><td><button type="button" class="remove-line" data-remove-line>×</button></td></tr></template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function applyIntent(intent) {
        document.querySelectorAll('[data-intent-field]').forEach(function (el) {
            var allowed = el.dataset.intentField.split(',');
            el.style.display = allowed.includes(intent) ? '' : 'none';
        });
    }
    var radios = document.querySelectorAll('[data-intent-radio]');
    radios.forEach(function (r) { r.addEventListener('change', function () { applyIntent(this.value); }); });
    var checked = document.querySelector('[data-intent-radio]:checked');
    applyIntent(checked ? checked.value : '{{ $currentIntent }}');
});
</script>
@endpush
@endsection
