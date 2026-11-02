<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Subsidiary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class InvoiceImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): Invoice
    {
        $this->seed();
        $subsidiary = Subsidiary::where('code', 'KSN')->firstOrFail();
        $admin = User::where('email', 'admin@ks.local')->firstOrFail();
        $client = Client::create(['name' => 'Client test']);

        return Invoice::create([
            'subsidiary_id' => $subsidiary->id,
            'client_id' => $client->id,
            'number' => 'FAC-KSN-2026-999999',
            'issue_date' => '2026-08-16',
            'currency' => 'GNF',
            'subtotal' => 100000,
            'tax_total' => 0,
            'total' => 100000,
            'issuer_name' => $subsidiary->name,
            'issuer_code' => $subsidiary->code,
            'group_name' => 'KS Groupe',
            'group_rccm' => 'GN.TCC.2026.B.04080',
            'group_nif' => '811840461',
            'group_rib' => '004.000.1095512502.18',
            'group_address' => 'Jardin du 02 Octobre à Kaloum',
            'client_name' => $client->name,
            'created_by' => $admin->id,
        ]);
    }

    public function test_final_invoice_cannot_be_updated_through_eloquent(): void
    {
        $invoice = $this->invoice();
        $this->expectException(LogicException::class);
        $invoice->update(['total' => 200000]);
    }

    public function test_final_invoice_cannot_be_deleted_through_eloquent(): void
    {
        $invoice = $this->invoice();
        $this->expectException(LogicException::class);
        $invoice->delete();
    }
}
