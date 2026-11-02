@extends('layouts.app')
@section('title',$sale->number)
@section('page-title',$sale->intent->label().' — '.$sale->number)
@section('content')
<div class="toolbar no-print">
    <a class="btn btn-secondary" href="{{ route('sales.index') }}">← Ventes</a>
    @if($sale->intent->value === 'direct')<a class="btn btn-primary" href="{{ route('sales.create') }}">+ Nouvelle vente</a>@endif
    <span class="spacer"></span>
    @if($sale->isEditable())
        <a class="btn btn-secondary" href="{{ route('sales.edit',$sale) }}">Modifier</a>
    @endif
    @if($sale->intent->value === 'quote')
        @if($sale->status->value === 'draft')
            <form method="POST" action="{{ route('sales.send',$sale) }}">@csrf<button class="btn btn-primary">Envoyer au client</button></form>
        @elseif($sale->status->value === 'sent')
            <form method="POST" action="{{ route('sales.advance',$sale) }}" onsubmit="return confirm('Confirmer ce devis ?')">@csrf<button class="btn btn-primary">Confirmer</button></form>
        @elseif($sale->status->value === 'confirmed')
            <form method="POST" action="{{ route('sales.convert',$sale) }}" onsubmit="return confirm('Créer la facture définitive ? Elle deviendra immuable et non supprimable.')">@csrf<button class="btn btn-success">Convertir en facture définitive</button></form>
        @endif
    @elseif($sale->intent->value === 'order' && $sale->nextStatus())
        <form method="POST" action="{{ route('sales.advance',$sale) }}" onsubmit="return confirm('Passer la commande à l’étape suivante ?')">@csrf<button class="btn btn-primary">Passer à : {{ $sale->nextStatus()->label() }}</button></form>
    @endif
    @if($sale->isCancellable())
        <form method="POST" action="{{ route('sales.cancel',$sale) }}" onsubmit="return confirm('Annuler ? Les réservations de stock seront libérées.')">@csrf<button class="btn btn-secondary">Annuler</button></form>
    @endif
    @if($sale->invoice)<a class="btn btn-success" href="{{ route('invoices.show',$sale->invoice) }}">Ouvrir la facture {{ $sale->invoice->number }}</a>@endif
    <button class="btn btn-secondary" data-print>Imprimer / PDF</button>
</div>

@if($sale->intent->value === 'order')
@php
    $steps = [\App\Enums\SaleStatus::DRAFT, \App\Enums\SaleStatus::CONFIRMED, \App\Enums\SaleStatus::PREPARATION, \App\Enums\SaleStatus::DELIVERED, \App\Enums\SaleStatus::INVOICED];
    $stepValues = array_map(fn($s) => $s->value, $steps);
    $currentIndex = array_search($sale->status->value, $stepValues, true);
@endphp
<div class="status-stepper">
@foreach($steps as $step)
    @php $stepIndex = array_search($step->value, $stepValues, true); $cls = $stepIndex < $currentIndex ? 'done' : ($stepIndex === $currentIndex ? 'current' : ''); @endphp
    <div class="status-step {{ $cls }}">{{ $step->label() }}</div>
@endforeach
</div>
@endif

@include('sales._document', ['sale' => $sale])
@endsection
