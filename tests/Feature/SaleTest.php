<?php

namespace Tests\Feature;

use App\Enums\SaleIntent;
use App\Enums\SaleStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockItem;
use App\Models\Subsidiary;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(Subsidiary $subsidiary, float $stock): Product
    {
        $product = Product::create([
            'subsidiary_id' => $subsidiary->id,
            'sku' => 'TEST-'.uniqid(),
            'name' => 'Produit test',
            'unit' => 'u',
            'sale_price' => 1000,
            'cost_price' => 500,
            'tax_rate' => 0,
            'low_stock_threshold' => 2,
            'active' => true,
        ]);
        StockItem::create(['product_id' => $product->id, 'quantity_on_hand' => $stock, 'reserved_quantity' => 0]);

        return $product;
    }

    public function test_confirmed_order_reserves_stock_without_reducing_physical_quantity(): void
    {
        $this->seed();
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $product = $this->makeProduct($subsidiary, 20);

        $sale = Sale::create([
            'subsidiary_id' => $subsidiary->id,
            'intent' => SaleIntent::ORDER->value,
            'status' => SaleStatus::DRAFT->value,
            'number' => 'CMD-KSN-2026-999999',
            'issue_date' => '2026-08-16',
            'subtotal' => 5000, 'tax_total' => 0, 'total' => 5000,
            'created_by' => $admin->id,
        ]);
        $sale->lines()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => 5, 'unit_price' => 1000, 'line_subtotal' => 5000, 'line_total' => 5000]);

        app(StockService::class)->reserveSale($sale, $admin);

        $stock = $product->stockItem()->firstOrFail();
        $this->assertSame(20.0, (float) $stock->quantity_on_hand);
        $this->assertSame(5.0, (float) $stock->reserved_quantity);
        $this->assertSame(15.0, $product->fresh()->availableQuantity());
    }

    public function test_direct_sale_decrements_stock_immediately_and_creates_an_invoice(): void
    {
        $this->seed();
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $product = $this->makeProduct($subsidiary, 10);

        $sale = Sale::create([
            'subsidiary_id' => $subsidiary->id,
            'intent' => SaleIntent::DIRECT->value,
            'status' => SaleStatus::CONFIRMED->value,
            'number' => 'VTE-KSN-2026-999999',
            'issue_date' => now()->toDateString(),
            'walk_in_client_name' => 'Client comptoir',
            'subtotal' => 3000, 'tax_total' => 0, 'total' => 3000,
            'created_by' => $admin->id,
            'confirmed_at' => now(),
        ]);
        $sale->lines()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => 3, 'unit_price' => 1000, 'line_subtotal' => 3000, 'line_total' => 3000]);

        app(StockService::class)->sellSale($sale, $admin);
        $invoice = app(InvoiceService::class)->createInvoiceFromSale($sale, $admin->id);

        $this->assertSame(3000.0, (float) $invoice->total);
        $this->assertSame($sale->id, $invoice->source_sale_id);
        $this->assertSame('Client comptoir', $invoice->client_name);

        $stock = $product->stockItem()->firstOrFail();
        $this->assertSame(7.0, (float) $stock->quantity_on_hand);
        $this->assertSame(0.0, (float) $stock->reserved_quantity);
    }

    public function test_order_delivery_consumes_stock_and_automatically_generates_an_invoice(): void
    {
        $this->seed();
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $product = $this->makeProduct($subsidiary, 20);

        $sale = Sale::create([
            'subsidiary_id' => $subsidiary->id,
            'intent' => SaleIntent::ORDER->value,
            'status' => SaleStatus::DRAFT->value,
            'number' => 'CMD-KSN-2026-999998',
            'issue_date' => '2026-08-16',
            'subtotal' => 5000, 'tax_total' => 0, 'total' => 5000,
            'created_by' => $admin->id,
        ]);
        $sale->lines()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => 5, 'unit_price' => 1000, 'line_subtotal' => 5000, 'line_total' => 5000]);

        $stock = app(StockService::class);
        $stock->reserveSale($sale, $admin);
        $stock->deliverSale($sale, $admin);

        $this->assertNull($sale->fresh()->invoice); // not yet invoiced by the stock step alone
        $invoice = app(InvoiceService::class)->createInvoiceFromSale($sale->fresh(), $admin->id);

        $this->assertSame(5000.0, (float) $invoice->total);
        $physical = $product->stockItem()->firstOrFail();
        $this->assertSame(15.0, (float) $physical->quantity_on_hand);
        $this->assertSame(SaleStatus::INVOICED, $sale->fresh()->status);
    }

    public function test_invoice_creation_from_sale_is_idempotent(): void
    {
        $this->seed();
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $client = Client::create(['subsidiary_id' => $subsidiary->id, 'name' => 'Client Test']);

        $sale = Sale::create([
            'subsidiary_id' => $subsidiary->id,
            'client_id' => $client->id,
            'intent' => SaleIntent::QUOTE->value,
            'status' => SaleStatus::CONFIRMED->value,
            'number' => 'PRO-KSN-2026-999997',
            'issue_date' => now()->toDateString(),
            'subtotal' => 1000, 'tax_total' => 0, 'total' => 1000,
            'created_by' => $admin->id,
        ]);
        $sale->lines()->create(['description' => 'Prestation', 'quantity' => 1, 'unit_price' => 1000, 'line_subtotal' => 1000, 'line_total' => 1000]);

        $invoices = app(InvoiceService::class);
        $first = $invoices->createInvoiceFromSale($sale, $admin->id);
        $second = $invoices->createInvoiceFromSale($sale->fresh(), $admin->id);

        $this->assertSame($first->id, $second->id);
    }

    public function test_cashier_cannot_create_quotes_but_can_sell(): void
    {
        $this->seed();
        $cashier = User::where('email', 'caissier@ks.local')->firstOrFail();
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $product = $this->makeProduct($subsidiary, 10);

        $this->actingAs($cashier)->post(route('sales.store'), [
            'subsidiary_id' => $subsidiary->id,
            'intent' => 'quote',
            'client_id' => Client::create(['name' => 'Client devis'])->id,
            'issue_date' => now()->toDateString(),
            'lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertForbidden();

        $this->actingAs($cashier)->post(route('sales.store'), [
            'subsidiary_id' => $subsidiary->id,
            'intent' => 'direct',
            'walk_in_client_name' => 'Client comptoir',
            'issue_date' => now()->toDateString(),
            'lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertRedirect();

        $sale = Sale::where('intent', 'direct')->latest('id')->firstOrFail();
        $invoice = $sale->invoice()->firstOrFail();
        $this->assertSame(0.0, $invoice->outstanding());
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertNull($invoice->payments()->first()->cash_movement_id);
    }

    public function test_direct_sale_without_a_named_client_defaults_to_client_comptoir(): void
    {
        $this->seed();
        $cashier = User::where('email', 'caissier@ks.local')->firstOrFail();
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $product = $this->makeProduct($subsidiary, 10);

        $this->actingAs($cashier)->post(route('sales.store'), [
            'subsidiary_id' => $subsidiary->id,
            'intent' => 'direct',
            'issue_date' => now()->toDateString(),
            'lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertRedirect();

        $sale = Sale::where('intent', 'direct')->latest('id')->firstOrFail();
        $this->assertSame('Client comptoir', $sale->walk_in_client_name);
    }

    public function test_a_user_cannot_create_a_sale_for_a_subsidiary_they_are_not_assigned_to(): void
    {
        $this->seed();
        $cashier = User::where('email', 'caissier@ks.local')->firstOrFail();
        $cashier->subsidiaries()->sync([]);
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $product = $this->makeProduct($subsidiary, 10);

        $this->actingAs($cashier)->post(route('sales.store'), [
            'subsidiary_id' => $subsidiary->id,
            'intent' => 'direct',
            'issue_date' => now()->toDateString(),
            'lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertForbidden();
    }
}
