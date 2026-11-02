<?php

namespace Tests\Feature;

use App\Enums\CashDirection;
use App\Models\AccountingAccount;
use App\Models\CashRegister;
use App\Models\User;
use App\Services\CashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashVarianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_calculates_theoretical_balance_and_variance(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $register = CashRegister::where('code', 'CAI-KSN')->firstOrFail();
        $expense = AccountingAccount::where('code', '602')->firstOrFail();
        $service = app(CashService::class);

        $session = $service->open($register, 10000, $admin);
        $service->addMovement($session, CashDirection::IN, 5000, null, null, 'Apport de caisse justifié', 'IN-TEST', $admin);
        $service->addMovement($session, CashDirection::OUT, 2000, null, $expense, 'Achat matières premières', 'OUT-TEST', $admin);
        $closed = $service->close($session, 12900, 'Comptage test', $admin);

        $this->assertSame(13000.0, (float) $closed->theoretical_closing);
        $this->assertSame(12900.0, (float) $closed->actual_closing);
        $this->assertSame(-100.0, (float) $closed->variance);
    }
}
