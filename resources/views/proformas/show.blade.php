@extends('layouts.app')
@section('title',$proforma->number)
@section('page-title','Facture proforma')
@section('content')
<div class="toolbar no-print"><a class="btn btn-secondary" href="{{ route('proformas.index') }}">← Proformas</a>@if($proforma->isEditable())<a class="btn btn-secondary" href="{{ route('proformas.edit',$proforma) }}">Modifier</a><form method="POST" action="{{ route('proformas.send',$proforma) }}">@csrf<button class="btn btn-primary">Envoyer au client</button></form><form method="POST" action="{{ route('proformas.convert',$proforma) }}" onsubmit="return confirm('Créer la facture définitive ? Elle deviendra immuable et non supprimable.')">@csrf<button class="btn btn-success">Convertir en facture définitive</button></form>@endif @if($proforma->invoice)<a class="btn btn-success" href="{{ route('invoices.show',$proforma->invoice) }}">Ouvrir la facture {{ $proforma->invoice->number }}</a>@endif<span class="spacer"></span><button class="btn btn-secondary" data-print>Imprimer / PDF</button></div>
@include('proformas._document',['proforma'=>$proforma])
@endsection
