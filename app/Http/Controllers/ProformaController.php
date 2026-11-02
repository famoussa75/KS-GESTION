<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\ProformaStatus;
use App\Mail\ProformaMail;
use App\Models\Client;
use App\Models\Product;
use App\Models\Proforma;
use App\Models\Subsidiary;
use App\Services\AuditService;
use App\Services\InvoiceService;
use App\Services\NumberingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProformaController extends Controller
{
    public function index(Request $request): View
    {
        $query = Proforma::with(['subsidiary', 'client', 'invoice'])->latest('issue_date');
        if ($request->filled('subsidiary_id')) $query->where('subsidiary_id', $request->subsidiary_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(fn ($b) => $b->where('number', 'like', "%{$q}%")->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%")));
        }
        return view('proformas.index', [
            'proformas' => $query->paginate(20)->withQueryString(),
            'subsidiaries' => Subsidiary::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('proformas.form', [
            'proforma' => new Proforma(['issue_date' => now(), 'valid_until' => now()->addDays(15)]),
            'subsidiaries' => Subsidiary::where('active', true)->orderBy('name')->get(),
            'clients' => Client::orderBy('name')->get(),
            'products' => Product::where('active', true)->orderBy('name')->get(),
            'editing' => false,
        ]);
    }

    public function store(Request $request, NumberingService $numbering, AuditService $audit): RedirectResponse
    {
        $data = $this->validateDocument($request);
        $subsidiary = Subsidiary::findOrFail($data['subsidiary_id']);
        $client = Client::findOrFail($data['client_id']);
        $this->validateOwnership($subsidiary, $client, $data['lines']);

        $proforma = DB::transaction(function () use ($data, $subsidiary, $numbering, $request) {
            $totals = $this->calculateLines($data['lines']);
            $proforma = Proforma::create([
                'subsidiary_id' => $subsidiary->id,
                'client_id' => $data['client_id'],
                'number' => $numbering->next(DocumentType::PROFORMA, $subsidiary, Carbon::parse($data['issue_date'])),
                'status' => ProformaStatus::DRAFT->value,
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'currency' => 'GNF',
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $this->persistLines($proforma, $totals['lines']);
            return $proforma;
        });
        $audit->log('proforma.created', $proforma, $subsidiary);
        return redirect()->route('proformas.show', $proforma)->with('success', 'Facture proforma créée.');
    }

    public function edit(Proforma $proforma): View
    {
        if (! $proforma->isEditable()) abort(409, 'Cette proforma a été convertie et ne peut plus être modifiée.');
        $proforma->load('lines');
        return view('proformas.form', [
            'proforma' => $proforma,
            'subsidiaries' => Subsidiary::where('active', true)->orderBy('name')->get(),
            'clients' => Client::orderBy('name')->get(),
            'products' => Product::where('active', true)->orderBy('name')->get(),
            'editing' => true,
        ]);
    }

    public function update(Request $request, Proforma $proforma, AuditService $audit): RedirectResponse
    {
        if (! $proforma->isEditable()) abort(409, 'Cette proforma a été convertie et ne peut plus être modifiée.');
        $data = $this->validateDocument($request);
        if ((int) $data['subsidiary_id'] !== $proforma->subsidiary_id) {
            throw ValidationException::withMessages(['subsidiary_id' => 'La filiale d’une proforma existante ne peut pas être changée.']);
        }
        $subsidiary = $proforma->subsidiary;
        $client = Client::findOrFail($data['client_id']);
        $this->validateOwnership($subsidiary, $client, $data['lines']);

        DB::transaction(function () use ($data, $proforma) {
            $totals = $this->calculateLines($data['lines']);
            $proforma->update([
                'client_id' => $data['client_id'],
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
            ]);
            $proforma->lines()->delete();
            $this->persistLines($proforma, $totals['lines']);
        });
        $audit->log('proforma.updated', $proforma, $proforma->subsidiary);
        return redirect()->route('proformas.show', $proforma)->with('success', 'Proforma mise à jour.');
    }

    public function show(Proforma $proforma): View
    {
        $proforma->load('subsidiary.groupProfile', 'client', 'lines.product', 'creator', 'invoice');
        return view('proformas.show', compact('proforma'));
    }

    public function sendToClient(Proforma $proforma, AuditService $audit): RedirectResponse
    {
        if ($proforma->status === ProformaStatus::CONVERTED) {
            return back()->withErrors(['send' => 'Cette proforma est déjà convertie.']);
        }
        $proforma->load('subsidiary.groupProfile', 'client', 'lines');
        if (! $proforma->client->email) {
            return back()->withErrors(['email' => 'Le client ne possède pas d’adresse e-mail.']);
        }

        $url = URL::temporarySignedRoute(
            'public.proformas.show',
            now()->addDays((int) config('ks.signed_document_link_days', 7)),
            ['proforma' => $proforma->id]
        );
        Mail::to($proforma->client->email)->send(new ProformaMail($proforma, $url));
        $proforma->update(['status' => ProformaStatus::SENT->value, 'sent_at' => now()]);
        $audit->log('proforma.sent', $proforma, $proforma->subsidiary, ['email' => $proforma->client->email]);
        return back()->with('success', 'Proforma envoyée au client.');
    }

    public function convert(Proforma $proforma, InvoiceService $service): RedirectResponse
    {
        $invoice = $service->convertFromProforma($proforma, auth()->id());
        return redirect()->route('invoices.show', $invoice)->with('success', 'Proforma convertie en facture définitive sans ressaisie.');
    }

    public function publicShow(Proforma $proforma): View
    {
        $proforma->load('subsidiary.groupProfile', 'client', 'lines.product');
        return view('public-docs.proforma', compact('proforma'));
    }

    private function validateDocument(Request $request): array
    {
        return $request->validate([
            'subsidiary_id' => ['required', 'exists:subsidiaries,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'exists:products,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function validateOwnership(Subsidiary $subsidiary, Client $client, array $lines): void
    {
        if ($client->subsidiary_id && $client->subsidiary_id !== $subsidiary->id) {
            throw ValidationException::withMessages(['client_id' => 'Ce client est rattaché à une autre filiale.']);
        }
        foreach ($lines as $line) {
            if (! empty($line['product_id'])) {
                $product = Product::findOrFail($line['product_id']);
                if ($product->subsidiary_id !== $subsidiary->id) {
                    throw ValidationException::withMessages(['lines' => 'Un produit sélectionné appartient à une autre filiale.']);
                }
            }
        }
    }

    private function calculateLines(array $lines): array
    {
        $result = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;
        foreach ($lines as $line) {
            $product = ! empty($line['product_id']) ? Product::find($line['product_id']) : null;
            $description = trim((string) ($line['description'] ?? '')) ?: ($product?->name ?? 'Article / service');
            $qty = (float) $line['quantity'];
            $price = (float) $line['unit_price'];
            $taxRate = (float) $line['tax_rate'];
            $lineSubtotal = round($qty * $price, 2);
            $lineTax = round($lineSubtotal * $taxRate / 100, 2);
            $lineTotal = round($lineSubtotal + $lineTax, 2);
            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
            $result[] = [
                'product_id' => $product?->id,
                'description' => $description,
                'quantity' => $qty,
                'unit_price' => $price,
                'tax_rate' => $taxRate,
                'line_subtotal' => $lineSubtotal,
                'line_tax' => $lineTax,
                'line_total' => $lineTotal,
            ];
        }
        return ['lines' => $result, 'subtotal' => round($subtotal, 2), 'tax_total' => round($taxTotal, 2), 'total' => round($subtotal + $taxTotal, 2)];
    }

    private function persistLines(Proforma $proforma, array $lines): void
    {
        foreach ($lines as $line) $proforma->lines()->create($line);
    }
}
