<?php

namespace Tests\Feature;

use App\Enums\CashDirection;
use App\Enums\OrderStatus;
use App\Models\AccountingAccount;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockItem;
use App\Models\Subsidiary;
use App\Models\User;
use App\Services\CashService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $code = 'KSN', bool $tracksExpiry = false): Product
    {
        $this->seed();
        $subsidiary = Subsidiary::where('code', $code)->firstOrFail();
        $subsidiary->update(['tracks_expiry' => $tracksExpiry, 'default_shelf_life_months' => 6]);
        $product = Product::create([
            'subsidiary_id' => $subsidiary->id, 'sku' => 'AUD-001', 'name' => 'Produit audit', 'unit' => 'u',
            'sale_price' => 1000, 'cost_price' => 500, 'tax_rate' => 0, 'low_stock_threshold' => 0, 'active' => true,
        ]);
        StockItem::create(['product_id' => $product->id, 'quantity_on_hand' => 0, 'reserved_quantity' => 0]);

        return $product;
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $this->seed();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@ks.local', 'password' => 'mauvais'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'admin@ks.local', 'password' => 'KSAdmin@2026!'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_expired_batch_cannot_be_reserved(): void
    {
        $product = $this->product('KSN', true);
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        StockBatch::create([
            'product_id' => $product->id, 'batch_number' => 'OLD', 'production_date' => now()->subYear()->toDateString(),
            'expiry_date' => now()->subDay()->toDateString(), 'quantity_on_hand' => 10, 'reserved_quantity' => 0,
        ]);
        StockItem::where('product_id', $product->id)->update(['quantity_on_hand' => 10]);

        $order = Order::create(['subsidiary_id' => $product->subsidiary_id, 'number' => 'CMD-T-1', 'status' => 'pending', 'order_date' => now(), 'created_by' => $admin->id]);
        $order->lines()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 1000]);

        $this->expectException(ValidationException::class);
        app(StockService::class)->reserveOrder($order, $admin);
    }

    public function test_cancelling_an_order_releases_reservations(): void
    {
        $product = $this->product();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        StockItem::where('product_id', $product->id)->update(['quantity_on_hand' => 10]);
        $order = Order::create(['subsidiary_id' => $product->subsidiary_id, 'number' => 'CMD-T-2', 'status' => 'pending', 'order_date' => now(), 'created_by' => $admin->id]);
        $order->lines()->create(['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 1000]);

        $this->actingAs($admin)->post(route('orders.advance', $order))->assertRedirect();
        $this->assertSame(4.0, (float) StockItem::where('product_id', $product->id)->value('reserved_quantity'));

        $this->actingAs($admin)->post(route('orders.cancel', $order))->assertRedirect();
        $this->assertSame(0.0, (float) StockItem::where('product_id', $product->id)->value('reserved_quantity'));
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
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
        $service->addMovement($session, CashDirection::OUT, 5000, null, $expense, 'Trop élevé', null, $admin);
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
