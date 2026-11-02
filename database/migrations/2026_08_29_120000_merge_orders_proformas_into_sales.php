<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidiary_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('intent', 16);
            $table->string('status', 24)->default('draft');
            $table->string('number')->unique();
            $table->string('currency', 3)->default('GNF');
            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            $table->date('requested_delivery_date')->nullable();
            $table->string('walk_in_client_name')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('preparation_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['subsidiary_id', 'status']);
            $table->index(['intent', 'status']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal', 18, 2)->default(0);
            $table->decimal('line_tax', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('source_sale_id')->nullable()->unique()->after('source_proforma_id')
                ->constrained('sales')->nullOnDelete();
        });

        // order_line_id was required until now; rebuild the table (portable across MySQL/SQLite,
        // no doctrine/dbal needed) so both order_line_id and the new sale_line_id are nullable.
        Schema::create('stock_reservations_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_line_id')->nullable()->constrained('order_lines')->cascadeOnDelete();
            $table->foreignId('sale_line_id')->nullable()->constrained('sale_lines')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });
        DB::statement('INSERT INTO stock_reservations_new (id, order_line_id, product_id, stock_batch_id, quantity, status, created_at, updated_at) SELECT id, order_line_id, product_id, stock_batch_id, quantity, status, created_at, updated_at FROM stock_reservations');
        Schema::drop('stock_reservations');
        Schema::rename('stock_reservations_new', 'stock_reservations');

        $orderLineIdMap = $this->migrateOrders();
        $saleIdByProformaId = $this->migrateProformas();

        // invoices are immutable via a MySQL trigger; lift it only for this one-time backfill.
        $isMysql = DB::getDriverName() === 'mysql';
        if ($isMysql) {
            DB::unprepared('DROP TRIGGER IF EXISTS ks_invoices_no_update');
        }

        foreach (DB::table('invoices')->whereNotNull('source_proforma_id')->get() as $invoice) {
            if (isset($saleIdByProformaId[$invoice->source_proforma_id])) {
                DB::table('invoices')->where('id', $invoice->id)
                    ->update(['source_sale_id' => $saleIdByProformaId[$invoice->source_proforma_id]]);
            }
        }

        if ($isMysql) {
            DB::unprepared("CREATE TRIGGER ks_invoices_no_update BEFORE UPDATE ON invoices FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Une facture définitive est immuable et ne peut pas être modifiée.'");
        }

        foreach (DB::table('stock_reservations')->get() as $reservation) {
            if (isset($orderLineIdMap[$reservation->order_line_id])) {
                DB::table('stock_reservations')->where('id', $reservation->id)
                    ->update(['sale_line_id' => $orderLineIdMap[$reservation->order_line_id]]);
            }
        }

        Schema::rename('order_lines', 'order_lines_legacy');
        Schema::rename('orders', 'orders_legacy');
        Schema::rename('proforma_lines', 'proforma_lines_legacy');
        Schema::rename('proformas', 'proformas_legacy');
    }

    private function migrateOrders(): array
    {
        $statusMap = ['pending' => 'draft', 'validated' => 'confirmed', 'preparation' => 'preparation', 'delivered' => 'delivered'];
        $orderLineIdMap = [];

        foreach (DB::table('orders')->get() as $order) {
            $lines = DB::table('order_lines')->where('order_id', $order->id)->get();
            $subtotal = 0.0;
            foreach ($lines as $line) {
                $subtotal += round((float) $line->quantity * (float) $line->unit_price, 2);
            }

            $saleId = DB::table('sales')->insertGetId([
                'subsidiary_id' => $order->subsidiary_id,
                'client_id' => $order->client_id,
                'intent' => 'order',
                'status' => $statusMap[$order->status] ?? 'draft',
                'number' => $order->number,
                'currency' => 'GNF',
                'issue_date' => $order->order_date,
                'valid_until' => null,
                'requested_delivery_date' => $order->requested_delivery_date,
                'walk_in_client_name' => null,
                'subtotal' => round($subtotal, 2),
                'tax_total' => 0,
                'total' => round($subtotal, 2),
                'notes' => $order->notes,
                'sent_at' => null,
                'confirmed_at' => $order->validated_at,
                'validated_at' => $order->validated_at,
                'preparation_at' => $order->preparation_at,
                'delivered_at' => $order->delivered_at,
                'invoiced_at' => null,
                'created_by' => $order->created_by,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ]);

            foreach ($lines as $line) {
                $lineSubtotal = round((float) $line->quantity * (float) $line->unit_price, 2);
                $productName = DB::table('products')->where('id', $line->product_id)->value('name');
                $saleLineId = DB::table('sale_lines')->insertGetId([
                    'sale_id' => $saleId,
                    'product_id' => $line->product_id,
                    'description' => $productName ?? 'Article',
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => 0,
                    'line_subtotal' => $lineSubtotal,
                    'line_tax' => 0,
                    'line_total' => $lineSubtotal,
                    'created_at' => $line->created_at,
                    'updated_at' => $line->updated_at,
                ]);
                $orderLineIdMap[$line->id] = $saleLineId;
            }
        }

        return $orderLineIdMap;
    }

    private function migrateProformas(): array
    {
        $saleIdByProformaId = [];

        foreach (DB::table('proformas')->get() as $proforma) {
            $lines = DB::table('proforma_lines')->where('proforma_id', $proforma->id)->get();
            $hasInvoice = DB::table('invoices')->where('source_proforma_id', $proforma->id)->exists();
            $statusMap = ['draft' => 'draft', 'sent' => 'sent', 'converted' => $hasInvoice ? 'invoiced' : 'confirmed'];

            $saleId = DB::table('sales')->insertGetId([
                'subsidiary_id' => $proforma->subsidiary_id,
                'client_id' => $proforma->client_id,
                'intent' => 'quote',
                'status' => $statusMap[$proforma->status] ?? 'draft',
                'number' => $proforma->number,
                'currency' => $proforma->currency,
                'issue_date' => $proforma->issue_date,
                'valid_until' => $proforma->valid_until,
                'requested_delivery_date' => null,
                'walk_in_client_name' => null,
                'subtotal' => $proforma->subtotal,
                'tax_total' => $proforma->tax_total,
                'total' => $proforma->total,
                'notes' => $proforma->notes,
                'sent_at' => $proforma->sent_at,
                'confirmed_at' => $proforma->converted_at,
                'validated_at' => null,
                'preparation_at' => null,
                'delivered_at' => null,
                'invoiced_at' => $proforma->converted_at,
                'created_by' => $proforma->created_by,
                'created_at' => $proforma->created_at,
                'updated_at' => $proforma->updated_at,
            ]);
            $saleIdByProformaId[$proforma->id] = $saleId;

            foreach ($lines as $line) {
                DB::table('sale_lines')->insert([
                    'sale_id' => $saleId,
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => $line->tax_rate,
                    'line_subtotal' => $line->line_subtotal,
                    'line_tax' => $line->line_tax,
                    'line_total' => $line->line_total,
                    'created_at' => $line->created_at,
                    'updated_at' => $line->updated_at,
                ]);
            }
        }

        return $saleIdByProformaId;
    }

    public function down(): void
    {
        Schema::rename('proformas_legacy', 'proformas');
        Schema::rename('proforma_lines_legacy', 'proforma_lines');
        Schema::rename('orders_legacy', 'orders');
        Schema::rename('order_lines_legacy', 'order_lines');

        Schema::create('stock_reservations_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_line_id')->constrained('order_lines')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });
        DB::statement('INSERT INTO stock_reservations_old (id, order_line_id, product_id, stock_batch_id, quantity, status, created_at, updated_at) SELECT id, order_line_id, product_id, stock_batch_id, quantity, status, created_at, updated_at FROM stock_reservations WHERE order_line_id IS NOT NULL');
        Schema::drop('stock_reservations');
        Schema::rename('stock_reservations_old', 'stock_reservations');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_sale_id');
        });

        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
    }
};
