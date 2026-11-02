<?php

namespace Tests\Feature;

use App\Enums\CashDirection;
use App\Enums\SaleStatus;
use App\Models\AccountingAccount;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBatch;
use App\Models\StockItem;
use App\Models\Subsidiary;
use App\Models\User;
use App\Services\CashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $code = 'KSN', bool $tracksExpiry = false, float $stock = 10): Product
    {
        $this->seed();
        $subsidiary = Subsidiary::where('code', $code)->firstOrFail();
        $subsidiary->update(['tracks_expiry' => $tracksExpiry, 'default_shelf_life_months' => 6]);
        $product = Product::create([
            'subsidiary_id' => $subsidiary->id, 'sku' => 'AUD-'.uniqid(), 'name' => 'Produit audit', 'unit' => 'u',
            'sale_price' => 1000, 'cost_price' => 500, 'tax_rate' => 0, 'low_stock_threshold' => 0, 'active' => true,
        ]);
        StockItem::create(['product_id' => $product->id, 'quantity_on_hand' => $stock, 'reserved_quantity' => 0]);

        return $product;
    }

    private function payload(Product $product, string $intent, float $qty = 1): array
    {
        return [
            'subsidiary_id' => $product->subsidiary_id,
            'intent' => $intent,
            'client_id' => Client::create(['name' => 'Client audit'])->id,
            'issue_date' => now()->toDateString(),
            'lines' => [['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => 1000]],
        ];
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $this->seed();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@ks.local', 'password' => 'mauvais'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'admin@ks.local', 'password' => 'KSAdmin@2026!'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_failed_direct_sale_leaves_no_orphan_sale(): void
    {
        $product = $this->product('KSN', false, 1);
        $cashier = User::where('email', 'caissier@ks.local')->firstOrFail();

        $this->actingAs($cashier)->post(route('sales.store'), $this->payload($product, 'direct', 5))->assertSessionHasErrors('stock');

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Invoice::count());
        $this->assertSame(1.0, (float) StockItem::where('product_id', $product->id)->value('quantity_on_hand'));
    }

    public function test_expired_batch_cannot_be_sold_directly(): void
    {
        $product = $this->product('KSN', true, 10);
        $cashier = User::where('email', 'caissier@ks.local')->firstOrFail();
        StockBatch::create([
            'product_id' => $product->id, 'batch_number' => 'OLD', 'production_date' => now()->subYear()->toDateString(),
            'expiry_date' => now()->subDay()->toDateString(), 'quantity_on_hand' => 10, 'reserved_quantity' => 0,
        ]);

        $this->actingAs($cashier)->post(route('sales.store'), $this->payload($product, 'direct', 2))->assertSessionHasErrors();

        $this->assertSame(0, Sale::count());
        $this->assertSame(10.0, (float) StockBatch::where('batch_number', 'OLD')->value('quantity_on_hand'));
    }

    public function test_cancelling_an_order_releases_reservations(): void
    {
        $product = $this->product();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();

        $this->actingAs($admin)->post(route('sales.store'), $this->payload($product, 'order', 4))->assertRedirect();
        $sale = Sale::firstOrFail();
        $this->actingAs($admin)->post(route('sales.advance', $sale))->assertRedirect();
        $this->assertSame(4.0, (float) StockItem::where('product_id', $product->id)->value('reserved_quantity'));

        $this->actingAs($admin)->post(route('sales.cancel', $sale))->assertRedirect();
        $this->assertSame(0.0, (float) StockItem::where('product_id', $product->id)->value('reserved_quantity'));
        $this->assertSame(SaleStatus::CANCELLED, $sale->fresh()->status);
    }

    public function test_direct_sale_cannot_be_cancelled(): void
    {
        $product = $this->product();
        $cashier = User::where('email', 'caissier@ks.local')->firstOrFail();
        $this->actingAs($cashier)->post(route('sales.store'), $this->payload($product, 'direct'))->assertRedirect();

        $this->actingAs($cashier)->post(route('sales.cancel', Sale::firstOrFail()))->assertSessionHasErrors('status');
        $this->assertSame(SaleStatus::INVOICED, Sale::firstOrFail()->status);
    }

    public function test_cashier_cannot_open_documents_of_unassigned_subsidiary(): void
    {
        $product = $this->product('KSN');
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $cashier = User::where('email', 'caissier@ks.local')->firstOrFail();
        $this->actingAs($admin)->post(route('sales.store'), $this->payload($product, 'direct'))->assertRedirect();
        $sale = Sale::firstOrFail();
        $invoice = $sale->invoice()->firstOrFail();

        $cashier->subsidiaries()->detach();

        $this->actingAs($cashier)->get(route('sales.show', $sale))->assertForbidden();
        $this->actingAs($cashier)->get(route('invoices.show', $invoice))->assertForbidden();
        $this->actingAs($cashier)->post(route('invoices.pay', $invoice), ['amount' => 1])->assertForbidden();
        $this->actingAs($cashier)->get(route('invoices.index'))->assertOk()->assertDontSee($invoice->number);
    }

    public function test_cash_out_cannot_exceed_theoretical_balance(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $register = CashRegister::where('code', 'CAI-KSN')->firstOrFail();
        $expense = AccountingAccount::where('code', '602')->firstOrFail();
        $service = app(CashService::class);
        $session = $service->open($register, 1000, $admin);

        $this->expectException(ValidationException::class);
        $service->addMovement($session, CashDirection::OUT, 5000, $expense, 'Trop élevé', null, $admin);
    }

    public function test_last_active_admin_cannot_be_demoted_or_deactivated(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'cashier', 'active' => 1,
        ])->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->isAdmin());
    }
}
