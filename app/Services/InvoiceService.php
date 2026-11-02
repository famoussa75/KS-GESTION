<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\ProformaStatus;
use App\Models\Invoice;
use App\Models\Proforma;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private NumberingService $numbering,
        private AccountingService $accounting,
        private AuditService $audit,
    ) {}

    public function convertFromProforma(Proforma $proforma, int $userId): Invoice
    {
        return DB::transaction(function () use ($proforma, $userId) {
            $locked = Proforma::query()->whereKey($proforma->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ProformaStatus::CONVERTED) {
                return $locked->invoice()->firstOrFail();
            }

            $locked->load('subsidiary.groupProfile', 'client', 'lines.product.revenueAccount');
            $issueDate = now()->startOfDay();
            $number = $this->numbering->next(DocumentType::INVOICE, $locked->subsidiary, $issueDate);
            $group = $locked->subsidiary->groupProfile;
            $client = $locked->client;

            $invoice = Invoice::create([
                'subsidiary_id' => $locked->subsidiary_id,
                'client_id' => $locked->client_id,
                'source_proforma_id' => $locked->id,
                'number' => $number,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $issueDate->copy()->addDays(30)->toDateString(),
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
                'client_name' => $client->name,
                'client_company' => $client->company,
                'client_email' => $client->email,
                'client_phone' => $client->phone,
                'client_address' => $client->address,
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
                'status' => ProformaStatus::CONVERTED->value,
                'converted_at' => now(),
            ]);

            $this->accounting->postInvoice($invoice);
            $this->audit->log('proforma.converted_to_invoice', $invoice, $locked->subsidiary, ['proforma' => $locked->number]);

            return $invoice->fresh('lines');
        });
    }
}
