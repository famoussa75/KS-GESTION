<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Subsidiary;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $query = Invoice::with(['subsidiary', 'payments'])->latest('issue_date');
        if ($request->filled('subsidiary_id')) $query->where('subsidiary_id', $request->subsidiary_id);
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(fn ($b) => $b->where('number', 'like', "%{$q}%")->orWhere('client_name', 'like', "%{$q}%"));
        }
        return view('invoices.index', [
            'invoices' => $query->paginate(20)->withQueryString(),
            'subsidiaries' => Subsidiary::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load('lines', 'payments.cashMovement', 'sourceSale', 'subsidiary.groupProfile');
        $publicUrl = URL::temporarySignedRoute('public.invoices.show', now()->addDays(7), ['invoice' => $invoice->id]);
        return view('invoices.show', compact('invoice', 'publicUrl'));
    }

    public function pay(Request $request, Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0']]);
        $invoices->recordPayment($invoice, (float) $data['amount'], $request->user()->id);
        return back()->with('success', 'Encaissement enregistré.');
    }

    public function publicShow(Invoice $invoice): View
    {
        $invoice->load('lines', 'payments');
        return view('public-docs.invoice', compact('invoice'));
    }
}
