@extends('layouts.app')
@section('title',$invoice->number)
@section('page-title','Facture définitive')
@section('content')
<div class="toolbar no-print"><a class="btn btn-secondary" href="{{ route('invoices.index') }}">← Factures</a><span class="badge badge-success">Document verrouillé</span>@if($invoice->sourceProforma)<a class="btn btn-secondary" href="{{ route('proformas.show',$invoice->sourceProforma) }}">Proforma source</a>@endif<span class="spacer"></span><button class="btn btn-secondary" onclick="navigator.clipboard.writeText('{{ $publicUrl }}')">Copier lien client</button><button class="btn btn-primary" data-print>Imprimer / PDF</button></div>
@include('invoices._document',['invoice'=>$invoice])
<div class="card no-print" style="margin-top:18px"><div class="card-header"><h3>Encaissements liés</h3><span class="badge {{ $invoice->outstanding()<=0.01?'badge-success':'badge-warning' }}">Solde : {{ number_format($invoice->outstanding(),0,',',' ') }} GNF</span></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Référence</th><th class="text-right">Montant</th></tr></thead><tbody>@forelse($invoice->payments as $payment)<tr><td>{{ $payment->paid_at->format('d/m/Y H:i') }}</td><td>{{ $payment->cashMovement?->reference ?: 'Mouvement caisse #'.$payment->cash_movement_id }}</td><td class="text-right fw-bold">{{ number_format($payment->amount,0,',',' ') }} GNF</td></tr>@empty<tr><td colspan="3" class="empty">Aucun encaissement enregistré.</td></tr>@endforelse</tbody></table></div></div>
@endsection
