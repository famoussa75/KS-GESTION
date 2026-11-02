<?php

namespace App\Services;

use App\Enums\CashDirection;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\CashMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountingService
{
    public function postInvoice(Invoice $invoice): JournalEntry
    {
        return DB::transaction(function () use ($invoice) {
            $invoice->loadMissing('lines.revenueAccount', 'subsidiary');
            $journal = $this->journal('VE');
            $clientAccount = $this->account('411');
            $taxAccount = $this->account('4431');

            $entry = JournalEntry::create([
                'subsidiary_id' => $invoice->subsidiary_id,
                'accounting_journal_id' => $journal->id,
                'entry_date' => $invoice->issue_date,
                'reference' => $invoice->number,
                'label' => 'Facture '.$invoice->number.' — '.$invoice->client_name,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'posted' => true,
                'created_by' => $invoice->created_by,
            ]);

            $entry->lines()->create([
                'accounting_account_id' => $clientAccount->id,
                'third_party' => $invoice->client_name,
                'label' => $invoice->number,
                'debit' => $invoice->total,
                'credit' => 0,
            ]);

            $credits = [];
            foreach ($invoice->lines as $line) {
                $account = $line->revenueAccount ?: $this->defaultRevenueAccount($invoice->subsidiary->code);
                $credits[$account->id] = ($credits[$account->id] ?? 0) + (float) $line->line_subtotal;
            }

            foreach ($credits as $accountId => $amount) {
                $entry->lines()->create([
                    'accounting_account_id' => $accountId,
                    'label' => 'Vente '.$invoice->number,
                    'debit' => 0,
                    'credit' => round($amount, 2),
                ]);
            }

            if ((float) $invoice->tax_total > 0) {
                $entry->lines()->create([
                    'accounting_account_id' => $taxAccount->id,
                    'label' => 'Taxe facturée '.$invoice->number,
                    'debit' => 0,
                    'credit' => $invoice->tax_total,
                ]);
            }

            $this->assertBalanced($entry);
            return $entry;
        });
    }

    public function postInvoicePayment(InvoicePayment $payment): JournalEntry
    {
        return DB::transaction(function () use ($payment) {
            $payment->loadMissing('invoice.subsidiary');
            $invoice = $payment->invoice;
            $journal = $this->journal('CA');
            $cashAccount = $this->account('571');
            $clientAccount = $this->account('411');

            $entry = JournalEntry::create([
                'subsidiary_id' => $invoice->subsidiary_id,
                'accounting_journal_id' => $journal->id,
                'entry_date' => $payment->paid_at->toDateString(),
                'reference' => $invoice->number,
                'label' => 'Encaissement '.$invoice->number.' — '.$invoice->client_name,
                'source_type' => InvoicePayment::class,
                'source_id' => $payment->id,
                'posted' => true,
                'created_by' => $invoice->created_by,
            ]);

            $entry->lines()->create(['accounting_account_id' => $cashAccount->id, 'label' => $entry->label, 'debit' => $payment->amount, 'credit' => 0]);
            $entry->lines()->create(['accounting_account_id' => $clientAccount->id, 'third_party' => $invoice->client_name, 'label' => $entry->label, 'debit' => 0, 'credit' => $payment->amount]);

            $this->assertBalanced($entry);
            return $entry;
        });
    }

    public function postCashMovement(CashMovement $movement): JournalEntry
    {
        return DB::transaction(function () use ($movement) {
            $movement->loadMissing('session.register.subsidiary', 'invoice', 'expenseAccount');
            $journal = $this->journal('CA');
            $cashAccount = $this->account('571');
            $clientAccount = $this->account('411');
            $subsidiary = $movement->session->register->subsidiary;

            $entry = JournalEntry::create([
                'subsidiary_id' => $subsidiary->id,
                'accounting_journal_id' => $journal->id,
                'entry_date' => $movement->occurred_at->toDateString(),
                'reference' => $movement->reference ?: 'MVT-'.$movement->id,
                'label' => $movement->justification ?: ($movement->invoice ? 'Règlement '.$movement->invoice->number : 'Mouvement de caisse'),
                'source_type' => CashMovement::class,
                'source_id' => $movement->id,
                'posted' => true,
                'created_by' => $movement->created_by,
            ]);

            if ($movement->direction === CashDirection::IN) {
                $counterpart = $movement->invoice ? $clientAccount : $this->account('758');
                $entry->lines()->create(['accounting_account_id' => $cashAccount->id, 'label' => $entry->label, 'debit' => $movement->amount, 'credit' => 0]);
                $entry->lines()->create(['accounting_account_id' => $counterpart->id, 'third_party' => $movement->invoice?->client_name, 'label' => $entry->label, 'debit' => 0, 'credit' => $movement->amount]);
            } else {
                $counterpart = $movement->expenseAccount ?: $this->account('6588');
                $entry->lines()->create(['accounting_account_id' => $counterpart->id, 'label' => $entry->label, 'debit' => $movement->amount, 'credit' => 0]);
                $entry->lines()->create(['accounting_account_id' => $cashAccount->id, 'label' => $entry->label, 'debit' => 0, 'credit' => $movement->amount]);
            }

            $this->assertBalanced($entry);
            return $entry;
        });
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::where('code', $code)->firstOrFail();
    }

    private function journal(string $code): AccountingJournal
    {
        return AccountingJournal::where('code', $code)->firstOrFail();
    }

    private function defaultRevenueAccount(string $subsidiaryCode): AccountingAccount
    {
        return $this->account(match (strtoupper($subsidiaryCode)) {
            'KSP' => '706',
            'JSS' => '702',
            default => '701',
        });
    }

    private function assertBalanced(JournalEntry $entry): void
    {
        $debit = (float) $entry->lines()->sum('debit');
        $credit = (float) $entry->lines()->sum('credit');

        if (abs($debit - $credit) > 0.01) {
            throw new RuntimeException("Écriture comptable déséquilibrée : débit {$debit}, crédit {$credit}.");
        }
    }
}
