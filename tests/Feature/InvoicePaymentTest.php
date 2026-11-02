<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Sale;
use App\Models\Subsidiary;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(Subsidiary $subsidiary, User $admin, float $total): Invoice
    {
        $sale = Sale::create([
            'subsidiary_id' => $subsidiary->id,
            'intent' => 'direct',
            'status' => 'confirmed',
            'number' => 'VTE-'.uniqid(),
            'issue_date' => now()->toDateString(),
            'walk_in_client_name' => 'Client comptoir',
            'subtotal' => $total, 'tax_total' => 0, 'total' => $total,
            'created_by' => $admin->id,
            'confirmed_at' => now(),
        ]);
        $sale->lines()->create(['description' => 'Article', 'quantity' => 1, 'unit_price' => $total, 'line_subtotal' => $total, 'line_total' => $total]);

        return app(InvoiceService::class)->createInvoiceFromSale($sale, $admin->id);
    }

    public function test_partial_then_full_payment_settles_the_invoice_without_a_cash_session(): void
    {
        $this->seed();
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $invoice = $this->makeInvoice($subsidiary, $admin, 10000);

        $invoices = app(InvoiceService::class);
        $invoices->recordPayment($invoice, 4000, $admin->id);
        $this->assertSame(6000.0, $invoice->fresh()->outstanding());

        $invoices->recordPayment($invoice->fresh(), 6000, $admin->id);
        $this->assertSame(0.0, $invoice->fresh()->outstanding());
        $this->assertSame(2, $invoice->fresh()->payments()->count());
    }

    public function test_payment_exceeding_outstanding_balance_is_rejected(): void
    {
        $this->seed();
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $invoice = $this->makeInvoice($subsidiary, $admin, 5000);

        $this->expectException(ValidationException::class);
        app(InvoiceService::class)->recordPayment($invoice, 5001, $admin->id);
    }

    public function test_cashier_can_pay_an_invoice_directly_and_stockkeeper_cannot(): void
    {
        $this->seed();
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $cashier = User::where('email', 'caissier@ks.local')->firstOrFail();
        $stockkeeper = User::where('email', 'magasinier@ks.local')->firstOrFail();
        $invoice = $this->makeInvoice($subsidiary, $admin, 8000);

        $this->actingAs($stockkeeper)->post(route('invoices.pay', $invoice), ['amount' => 8000])->assertForbidden();
        $this->actingAs($cashier)->post(route('invoices.pay', $invoice), ['amount' => 8000])->assertRedirect();
        $this->assertSame(0.0, $invoice->fresh()->outstanding());
    }
}
