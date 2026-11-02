<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\SaleIntent;
use App\Enums\SaleStatus;
use App\Mail\ProformaMail;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subsidiary;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InvoiceService;
use App\Services\NumberingService;
use App\Services\StockService;
use App\Services\SubsidiaryAccess;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request, SubsidiaryAccess $access): View
    {
        $accessible = $access->list($request->user());
        $query = Sale::with(['subsidiary', 'client', 'invoice'])
            ->whereIn('subsidiary_id', $accessible->pluck('id'))
            ->latest('issue_date')->latest('id');

        if ($request->user()->isCashier()) {
            $query->whereIn('intent', [SaleIntent::DIRECT->value, SaleIntent::ORDER->value]);
        } elseif (! $request->user()->isAdmin()) {
            $query->where('intent', SaleIntent::DIRECT->value);
        }
        if ($request->filled('intent')) $query->where('intent', $request->intent);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('subsidiary_id')) $query->where('subsidiary_id', $request->subsidiary_id);
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(fn ($b) => $b->where('number', 'like', "%{$q}%")->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%")));
        }

        return view('sales.index', [
            'sales' => $query->paginate(20)->withQueryString(),
            'subsidiaries' => $accessible,
        ]);
    }

    public function create(Request $request, SubsidiaryAccess $access): View
    {
        $user = $request->user();
        $allowedIntents = match (true) {
            $user->isAdmin() => SaleIntent::cases(),
            $user->isCashier() => [SaleIntent::DIRECT, SaleIntent::ORDER],
            default => [SaleIntent::DIRECT],
        };
        $requestedIntent = $request->string('intent')->toString();
        $defaultIntent = collect($allowedIntents)->first(fn (SaleIntent $intent) => $intent->value === $requestedIntent)?->value
            ?? ($user->isAdmin() ? SaleIntent::QUOTE->value : SaleIntent::DIRECT->value);
        $subsidiaries = $access->list($request->user());

        return view('sales.form', [
            'sale' => new Sale(['issue_date' => now(), 'valid_until' => now()->addDays(15), 'subsidiary_id' => $subsidiaries->count() === 1 ? $subsidiaries->first()->id : null]),
            'subsidiaries' => $subsidiaries,
            'clients' => Client::orderBy('name')->get(),
            'products' => Product::with('stockItem')->where('active', true)->orderBy('name')->get(),
            'editing' => false,
            'allowedIntents' => $allowedIntents,
            'defaultIntent' => $defaultIntent,
        ]);
    }

    public function store(Request $request, NumberingService $numbering, StockService $stock, InvoiceService $invoices, AuditService $audit, SubsidiaryAccess $access): RedirectResponse
    {
        $data = $this->validateDocument($request);
        $intent = SaleIntent::from($data['intent']);
        $this->authorizeIntent($request->user(), $intent);
        $data = $this->clearFieldsNotBelongingToIntent($data, $intent);
        if ($intent === SaleIntent::DIRECT && empty($data['client_id']) && ! trim((string) ($data['walk_in_client_name'] ?? ''))) {
            $data['walk_in_client_name'] = 'Client comptoir';
        }

        $subsidiary = Subsidiary::findOrFail($data['subsidiary_id']);
        $access->assert($request->user(), $subsidiary->id);
        $client = ! empty($data['client_id']) ? Client::findOrFail($data['client_id']) : null;
        $this->validateOwnership($subsidiary, $client, $data['lines']);

        $sale = DB::transaction(function () use ($data, $subsidiary, $numbering, $request, $intent, $stock, $invoices) {
            $totals = $this->calculateLines($data['lines']);
            $docType = match ($intent) {
                SaleIntent::QUOTE => DocumentType::PROFORMA,
                SaleIntent::ORDER => DocumentType::ORDER,
                SaleIntent::DIRECT => DocumentType::SALE,
            };

            $sale = Sale::create([
                'subsidiary_id' => $subsidiary->id,
                'client_id' => $data['client_id'] ?? null,
                'intent' => $intent->value,
                'status' => SaleStatus::DRAFT->value,
                'number' => $numbering->next($docType, $subsidiary, Carbon::parse($data['issue_date'])),
                'currency' => 'GNF',
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'walk_in_client_name' => $data['walk_in_client_name'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $this->persistLines($sale, $totals['lines']);

            // Vente directe : tout ou rien. Si le stock manque, ni vente, ni numéro, ni facture.
            if ($intent === SaleIntent::DIRECT) {
                $sale->update(['status' => SaleStatus::CONFIRMED->value, 'confirmed_at' => now()]);
                $stock->sellSale($sale, $request->user());
                $invoice = $invoices->createInvoiceFromSale($sale, $request->user()->id);
                $invoices->recordPayment($invoice, (float) $invoice->total, $request->user()->id);
                $sale->refresh();
            }

            return $sale;
        });

        $audit->log('sale.created', $sale, $subsidiary);
        return redirect()->route('sales.show', $sale)->with('success', $this->createdMessage($intent));
    }

    public function edit(Sale $sale): View
    {
        $this->authorizeSale(auth()->user(), $sale);
        if (! $sale->isEditable()) abort(409, 'Cette vente ne peut plus être modifiée à ce stade.');
        $sale->load('lines');
        return view('sales.form', [
            'sale' => $sale,
            'subsidiaries' => Subsidiary::where('active', true)->orderBy('name')->get(),
            'clients' => Client::orderBy('name')->get(),
            'products' => Product::with('stockItem')->where('active', true)->orderBy('name')->get(),
            'editing' => true,
            'allowedIntents' => [$sale->intent],
            'defaultIntent' => $sale->intent->value,
        ]);
    }

    public function update(Request $request, Sale $sale, AuditService $audit): RedirectResponse
    {
        $this->authorizeSale($request->user(), $sale);
        if (! $sale->isEditable()) abort(409, 'Cette vente ne peut plus être modifiée à ce stade.');

        $data = $this->validateDocument($request);
        if ((int) $data['subsidiary_id'] !== $sale->subsidiary_id) {
            throw ValidationException::withMessages(['subsidiary_id' => 'La filiale d’une vente existante ne peut pas être changée.']);
        }
        $data = $this->clearFieldsNotBelongingToIntent($data, $sale->intent);
        $subsidiary = $sale->subsidiary;
        $client = ! empty($data['client_id']) ? Client::findOrFail($data['client_id']) : null;
        $this->validateOwnership($subsidiary, $client, $data['lines']);

        DB::transaction(function () use ($data, $sale) {
            $totals = $this->calculateLines($data['lines']);
            $sale->update([
                'client_id' => $data['client_id'] ?? null,
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'walk_in_client_name' => $data['walk_in_client_name'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
            ]);
            $sale->lines()->delete();
            $this->persistLines($sale, $totals['lines']);
        });
        $audit->log('sale.updated', $sale, $sale->subsidiary);
        return redirect()->route('sales.show', $sale)->with('success', 'Vente mise à jour.');
    }

    public function show(Sale $sale): View
    {
        $this->authorizeSale(auth()->user(), $sale);
        $sale->load('subsidiary.groupProfile', 'client', 'lines.product.stockItem', 'lines.reservations.batch', 'creator', 'invoice');
        return view('sales.show', compact('sale'));
    }

    public function advance(Request $request, Sale $sale, StockService $stock, InvoiceService $invoices, AuditService $audit): RedirectResponse
    {
        $this->authorizeSale($request->user(), $sale);
        $next = $sale->nextStatus();
        if (! $next) {
            return back()->withErrors(['status' => 'Cette vente ne peut plus avancer manuellement.']);
        }

        DB::transaction(function () use ($request, $sale, $next, $stock) {
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($locked->nextStatus() !== $next) {
                throw ValidationException::withMessages(['status' => 'Le statut a été modifié par un autre utilisateur. Rechargez la page.']);
            }

            $timestamps = ['status' => $next->value];
            if ($next === SaleStatus::SENT) $timestamps['sent_at'] = now();
            if ($next === SaleStatus::CONFIRMED) {
                $timestamps['confirmed_at'] = now();
                $timestamps['validated_at'] = now();
                if ($locked->intent === SaleIntent::ORDER) $stock->reserveSale($locked, $request->user());
            }
            if ($next === SaleStatus::PREPARATION) $timestamps['preparation_at'] = now();
            if ($next === SaleStatus::DELIVERED) {
                $timestamps['delivered_at'] = now();
                $stock->deliverSale($locked, $request->user());
            }

            $locked->update($timestamps);
        });

        $sale->refresh();

        if ($sale->intent === SaleIntent::ORDER && $sale->status === SaleStatus::DELIVERED) {
            $invoices->createInvoiceFromSale($sale, $request->user()->id);
            $sale->refresh();
        }

        $audit->log('sale.status_changed', $sale, $sale->subsidiary, ['status' => $sale->status->value]);
        return back()->with('success', 'Vente passée au statut : '.$sale->status->label().'.');
    }

    public function cancel(Request $request, Sale $sale, StockService $stock, AuditService $audit): RedirectResponse
    {
        $this->authorizeSale($request->user(), $sale);

        DB::transaction(function () use ($request, $sale, $stock) {
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isCancellable()) {
                throw ValidationException::withMessages(['status' => 'Cette vente ne peut plus être annulée.']);
            }
            if ($locked->intent === SaleIntent::ORDER) {
                $stock->releaseSale($locked, $request->user());
            }
            $locked->update(['status' => SaleStatus::CANCELLED->value]);
        });

        $sale->refresh();
        $audit->log('sale.cancelled', $sale, $sale->subsidiary);
        return back()->with('success', 'Annulé. Les réservations de stock éventuelles ont été libérées.');
    }

    public function sendToClient(Sale $sale, AuditService $audit): RedirectResponse
    {
        $this->authorizeSale(auth()->user(), $sale);
        if ($sale->intent !== SaleIntent::QUOTE) abort(404);
        if ($sale->status !== SaleStatus::DRAFT) {
            return back()->withErrors(['send' => 'Ce devis a déjà été envoyé ou traité.']);
        }
        $sale->load('subsidiary.groupProfile', 'client', 'lines');
        if (! $sale->client?->email) {
            return back()->withErrors(['email' => 'Le client ne possède pas d’adresse e-mail.']);
        }

        $url = URL::temporarySignedRoute(
            'public.proformas.show',
            now()->addDays((int) config('ks.signed_document_link_days', 7)),
            ['sale' => $sale->id]
        );
        Mail::to($sale->client->email)->send(new ProformaMail($sale, $url));
        $sale->update(['status' => SaleStatus::SENT->value, 'sent_at' => now()]);
        $audit->log('sale.sent', $sale, $sale->subsidiary, ['email' => $sale->client->email]);
        return back()->with('success', 'Devis envoyé au client.');
    }

    public function convert(Sale $sale, InvoiceService $invoices): RedirectResponse
    {
        $this->authorizeSale(auth()->user(), $sale);
        if ($sale->intent !== SaleIntent::QUOTE || $sale->status !== SaleStatus::CONFIRMED) {
            return back()->withErrors(['convert' => 'Ce devis doit être confirmé avant conversion.']);
        }
        $invoice = $invoices->createInvoiceFromSale($sale, auth()->id());
        return redirect()->route('invoices.show', $invoice)->with('success', 'Devis converti en facture définitive.');
    }

    public function publicShow(Sale $sale): View
    {
        $sale->load('subsidiary.groupProfile', 'client', 'lines.product');
        return view('public-docs.proforma', ['proforma' => $sale]);
    }

    /**
     * The unified form keeps quote/order/direct-only fields in the DOM (just hidden via CSS)
     * so a stale default value can be submitted for a field that doesn't apply to this intent.
     */
    private function clearFieldsNotBelongingToIntent(array $data, SaleIntent $intent): array
    {
        if ($intent !== SaleIntent::QUOTE) $data['valid_until'] = null;
        if ($intent !== SaleIntent::ORDER) $data['requested_delivery_date'] = null;
        if ($intent !== SaleIntent::DIRECT) $data['walk_in_client_name'] = null;

        return $data;
    }

    private function authorizeIntent(User $user, SaleIntent $intent): void
    {
        if ($intent === SaleIntent::DIRECT) return;
        if ($intent === SaleIntent::ORDER && ($user->isAdmin() || $user->isCashier())) return;
        if ($user->isAdmin()) return;

        abort(403, 'Seul un administrateur peut créer un devis. Le caissier peut créer des commandes à livrer.');
    }

    private function authorizeSale(User $user, Sale $sale): void
    {
        app(SubsidiaryAccess::class)->assert($user, $sale->subsidiary_id);

        if ($sale->intent === SaleIntent::DIRECT) return;
        if ($sale->intent === SaleIntent::ORDER && ($user->isAdmin() || $user->isCashier())) return;
        if ($user->isAdmin()) return;

        abort(403, 'Vous ne disposez pas des droits nécessaires pour cette vente.');
    }

    private function createdMessage(SaleIntent $intent): string
    {
        return match ($intent) {
            SaleIntent::QUOTE => 'Devis créé.',
            SaleIntent::ORDER => 'Commande créée.',
            SaleIntent::DIRECT => 'Vente enregistrée, facture générée.',
        };
    }

    private function validateDocument(Request $request): array
    {
        return $request->validate([
            'subsidiary_id' => ['required', 'exists:subsidiaries,id'],
            'intent' => ['required', 'in:quote,direct,order'],
            'client_id' => ['nullable', 'exists:clients,id', 'required_if:intent,quote'],
            'walk_in_client_name' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'exists:products,id', 'required_if:intent,order'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function validateOwnership(Subsidiary $subsidiary, ?Client $client, array $lines): void
    {
        if ($client && $client->subsidiary_id && $client->subsidiary_id !== $subsidiary->id) {
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
            $taxRate = (float) ($line['tax_rate'] ?? 0);
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

    private function persistLines(Sale $sale, array $lines): void
    {
        foreach ($lines as $line) $sale->lines()->create($line);
    }
}
