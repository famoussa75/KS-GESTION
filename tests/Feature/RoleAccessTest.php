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
        $this->actingAs($cashier)->get(route('products.index'))->assertForbidden();
    }

    public function test_stockkeeper_can_access_stock_but_not_catalog_management_or_cash(): void
    {
        $this->seed();
        $stockkeeper = User::where('email', 'magasinier@ks.local')->firstOrFail();

        $this->actingAs($stockkeeper)->get(route('stock.index'))->assertOk();
        $this->actingAs($stockkeeper)->get(route('products.index'))->assertForbidden();
        $this->actingAs($stockkeeper)->get(route('cash.index'))->assertForbidden();
        $this->actingAs($stockkeeper)->get(route('sales.index'))->assertForbidden();
    }

    public function test_every_operational_role_can_access_the_shared_alert_center(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $cashier = User::where('email', 'caissier@ks.local')->firstOrFail();
        $stockkeeper = User::where('email', 'magasinier@ks.local')->firstOrFail();

        $this->actingAs($admin)->get(route('alerts.index'))->assertOk();
        $this->actingAs($cashier)->get(route('alerts.index'))->assertOk();
        $this->actingAs($stockkeeper)->get(route('alerts.index'))->assertOk();
    }

    public function test_only_admin_can_create_a_subsidiary(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $cashier = User::where('email', 'caissier@ks.local')->firstOrFail();

        $this->actingAs($admin)->get(route('subsidiaries.create'))->assertOk();
        $this->actingAs($cashier)->get(route('subsidiaries.create'))->assertForbidden();
    }
}
