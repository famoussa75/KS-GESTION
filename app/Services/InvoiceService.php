<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\SaleStatus;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private NumberingService $numbering,
        private AccountingService $accounting,
        private AuditService $audit,
    ) {}

    public function recordPayment(Invoice $invoice, float $amount, int $userId): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $amount, $userId) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Le montant doit être positif.']);
            }
            if ($amount - $locked->outstanding() > 0.01) {
                throw ValidationException::withMessages(['amount' => 'Le montant dépasse le solde restant de la facture.']);
            }

            $payment = InvoicePayment::create([
                'invoice_id' => $locked->id,
                'cash_movement_id' => null,
                'amount' => $amount,
                'paid_at' => now(),
            ]);

            $this->accounting->postInvoicePayment($payment);
            $this->audit->log('invoice.paid', $payment, $locked->subsidiary, ['invoice' => $locked->number, 'amount' => $amount, 'by' => $userId]);

            return $payment;
        });
    }

    public function createInvoiceFromSale(Sale $sale, int $userId): Invoice
    {
        return DB::transaction(function () use ($sale, $userId) {
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            $existing = $locked->invoice()->first();
            if ($existing) {
                return $existing;
            }

            $locked->load('subsidiary.groupProfile', 'client', 'lines.product.revenueAccount');
            $issueDate = now()->startOfDay();
            $number = $this->numbering->next(DocumentType::INVOICE, $locked->subsidiary, $issueDate);
            $group = $locked->subsidiary->groupProfile;
            $client = $locked->client;

            $invoice = Invoice::create([
                'subsidiary_id' => $locked->subsidiary_id,
                'client_id' => $locked->client_id,
                'source_sale_id' => $locked->id,
                'number' => $number,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $locked->intent->value === 'direct' ? null : $issueDate->copy()->addDays(30)->toDateString(),
                'currency' => $locked->currency,
                'subtotal' => $locked->subtotal,
                'tax_total' => $locked->tax_total,
                'total' => $locked->total,
                'notes' => $locked->notes,
                'issuer_name' => $locked->subsidiary->name,
                'issuer_code' => $locked->subsidiary->code,
                'issuer_logo_path' => $locked->subsidiary->logo_path,
                'group_name' => $group->name,
                'group_rccm' => $group->rccm,
                'group_nif' => $group->nif,
                'group_rib' => $group->rib,
                'group_address' => $group->address,
                'client_name' => $client->name ?? ($locked->walk_in_client_name ?: 'Client comptoir'),
                'client_company' => $client->company ?? null,
                'client_email' => $client->email ?? null,
                'client_phone' => $client->phone ?? null,
                'client_address' => $client->address ?? null,
                'created_by' => $userId,
            ]);

            foreach ($locked->lines as $line) {
                $invoice->lines()->create([
                    'product_id' => $line->product_id,
                    'sku' => $line->product?->sku,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => $line->tax_rate,
                    'line_subtotal' => $line->line_subtotal,
                    'line_tax' => $line->line_tax,
                    'line_total' => $line->line_total,
                    'revenue_account_id' => $line->product?->revenue_account_id,
                ]);
            }

            $locked->update([
                'status' => SaleStatus::INVOICED->value,
                'invoiced_at' => now(),
            ]);

            $this->accounting->postInvoice($invoice);
            $this->audit->log('sale.invoiced', $invoice, $locked->subsidiary, ['sale' => $locked->number]);

            return $invoice->fresh('lines');
        });
    }
}
