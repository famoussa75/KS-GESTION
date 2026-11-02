<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_access_only_cash_module_among_operational_modules(): void
    {
        $this->seed();
        $cashier = User::where('email', 'caissier@ks.local')->firstOrFail();

        $this->actingAs($cashier)->get(route('cash.index'))->assertOk();
        $this->actingAs($cashier)->get(route('stock.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('orders.index'))->assertForbidden();
    }

    public function test_stockkeeper_can_access_stock_but_not_catalog_management_or_cash(): void
    {
        $this->seed();
        $stockkeeper = User::where('email', 'magasinier@ks.local')->firstOrFail();

        $this->actingAs($stockkeeper)->get(route('stock.index'))->assertOk();
        $this->actingAs($stockkeeper)->get(route('products.index'))->assertForbidden();
        $this->actingAs($stockkeeper)->get(route('cash.index'))->assertForbidden();
    }
}
