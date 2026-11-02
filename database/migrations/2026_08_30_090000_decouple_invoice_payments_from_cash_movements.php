<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('cash_movement_id')->nullable()->unique()->constrained('cash_movements')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        DB::statement('INSERT INTO invoice_payments_new (id, invoice_id, cash_movement_id, amount, paid_at, created_at, updated_at) SELECT id, invoice_id, cash_movement_id, amount, paid_at, created_at, updated_at FROM invoice_payments');

        Schema::drop('invoice_payments');
        Schema::rename('invoice_payments_new', 'invoice_payments');
    }

    public function down(): void
    {
        Schema::create('invoice_payments_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('cash_movement_id')->unique()->constrained('cash_movements')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        DB::statement('INSERT INTO invoice_payments_old (id, invoice_id, cash_movement_id, amount, paid_at, created_at, updated_at) SELECT id, invoice_id, cash_movement_id, amount, paid_at, created_at, updated_at FROM invoice_payments WHERE cash_movement_id IS NOT NULL');

        Schema::drop('invoice_payments');
        Schema::rename('invoice_payments_old', 'invoice_payments');
    }
};
