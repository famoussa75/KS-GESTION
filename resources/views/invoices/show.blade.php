@extends('layouts.app')
@section('title',$invoice->number)
@section('page-title','Facture définitive')
@section('content')
<div class="toolbar no-print"><a class="btn btn-secondary" href="{{ route('invoices.index') }}">← Factures</a><span class="badge badge-success">Document verrouillé</span>@if($invoice->sourceSale && $invoice->sourceSale->intent->value === 'quote')<a class="btn btn-secondary" href="{{ route('sales.show',$invoice->sourceSale) }}">Devis source</a>@endif<span class="spacer"></span><button class="btn btn-secondary" onclick="navigator.clipboard.writeText('{{ $publicUrl }}')">Copier lien client</button><button class="btn btn-primary" data-print>Imprimer / PDF</button></div>
@include('invoices._document',['invoice'=>$invoice])
<div class="card no-print" style="margin-top:18px"><div class="card-header"><h3>Encaissements</h3><span class="badge {{ $invoice->outstanding()<=0.01?'badge-success':'badge-warning' }}">Solde : {{ number_format($invoice->outstanding(),0,',',' ') }} GNF</span></div>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Référence</th><th class="text-right">Montant</th></tr></thead><tbody>@forelse($invoice->payments as $payment)<tr><td>{{ $payment->paid_at->format('d/m/Y H:i') }}</td><td>{{ $payment->cashMovement?->reference ?: 'Paiement direct' }}</td><td class="text-right fw-bold">{{ number_format($payment->amount,0,',',' ') }} GNF</td></tr>@empty<tr><td colspan="3" class="empty">Aucun encaissement enregistré.</td></tr>@endforelse</tbody></table></div>
@if($invoice->outstanding() > 0.01)
<div class="card-body"><form method="POST" action="{{ route('invoices.pay',$invoice) }}" style="display:flex;gap:10px;align-items:flex-end">@csrf
<div class="form-group"><label>Montant à encaisser</label><input type="number" step="0.01" min="0.01" max="{{ $invoice->outstanding() }}" name="amount" value="{{ number_format($invoice->outstanding(),2,'.','') }}" required></div>
<button class="btn btn-success">Encaisser</button>
</form></div>
@endif
</div>
@endsection
