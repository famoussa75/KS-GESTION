<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\Subsidiary;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validated_order_reserves_stock_without_reducing_physical_quantity(): void
    {
        $this->seed();
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $product = Product::create([
            'subsidiary_id' => $subsidiary->id,
            'sku' => 'TEST-001',
            'name' => 'Produit test',
            'unit' => 'u',
            'sale_price' => 1000,
            'cost_price' => 500,
            'tax_rate' => 0,
            'low_stock_threshold' => 2,
            'active' => true,
        ]);
        StockItem::create(['product_id' => $product->id, 'quantity_on_hand' => 20, 'reserved_quantity' => 0]);

        $order = Order::create([
            'subsidiary_id' => $subsidiary->id,
            'number' => 'CMD-KSN-2026-999999',
            'status' => OrderStatus::PENDING->value,
            'order_date' => '2026-08-16',
            'created_by' => $admin->id,
        ]);
        $order->lines()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 1000]);

        app(StockService::class)->reserveOrder($order, $admin);

        $stock = $product->stockItem()->firstOrFail();
        $this->assertSame(20.0, (float) $stock->quantity_on_hand);
        $this->assertSame(5.0, (float) $stock->reserved_quantity);
        $this->assertSame(15.0, $product->fresh()->availableQuantity());
    }
}
