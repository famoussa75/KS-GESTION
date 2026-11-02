<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rccm')->nullable();
            $table->string('nif')->nullable();
            $table->string('rib')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->default('images/ks-logo.png');
            $table->string('icon_path')->default('images/ks-icon.png');
            $table->string('currency', 3)->default('GNF');
            $table->timestamps();
        });

        Schema::create('subsidiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 12)->unique();
            $table->string('slug')->unique();
            $table->string('activity');
            $table->string('logo_path')->nullable();
            $table->boolean('tracks_expiry')->default(false);
            $table->unsignedTinyInteger('default_shelf_life_months')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 32)->default('stockkeeper');
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('subsidiary_user', function (Blueprint $table) {
            $table->foreignId('subsidiary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['subsidiary_id', 'user_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidiary_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('nif')->nullable();
            $table->timestamps();
            $table->index(['subsidiary_id', 'name']);
        });

        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('label');
            $table->unsignedTinyInteger('class');
            $table->string('type', 32); // asset, liability, expense, revenue, equity
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidiary_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 20)->default('unité');
            $table->decimal('sale_price', 18, 2)->default(0);
            $table->decimal('cost_price', 18, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('low_stock_threshold', 18, 3)->default(0);
            $table->foreignId('revenue_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['subsidiary_id', 'sku']);
        });

        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 18, 3)->default(0);
            $table->decimal('reserved_quantity', 18, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->date('production_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity_on_hand', 18, 3)->default(0);
            $table->decimal('reserved_quantity', 18, 3)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'batch_number']);
            $table->index(['product_id', 'expiry_date']);
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidiary_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
            $table->unique(['subsidiary_id', 'document_type', 'year'], 'doc_sequence_unique');
        });

        Schema::create('proformas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidiary_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('number')->unique();
            $table->string('status', 24)->default('draft');
            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            $table->string('currency', 3)->default('GNF');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('proforma_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proforma_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal', 18, 2);
            $table->decimal('line_tax', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidiary_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_proforma_id')->nullable()->unique()->constrained('proformas')->nullOnDelete();
            $table->string('number')->unique();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('GNF');
            $table->decimal('subtotal', 18, 2);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->text('notes')->nullable();
            // Immutable identity snapshots for legal document integrity.
            $table->string('issuer_name');
            $table->string('issuer_code', 12);
            $table->string('issuer_logo_path')->nullable();
            $table->string('group_name');
            $table->string('group_rccm')->nullable();
            $table->string('group_nif')->nullable();
            $table->string('group_rib')->nullable();
            $table->string('group_address')->nullable();
            $table->string('client_name');
            $table->string('client_company')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->text('client_address')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('description');
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal', 18, 2);
            $table->decimal('line_tax', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->foreignId('revenue_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidiary_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number')->unique();
            $table->decimal('opening_float', 18, 2);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('theoretical_closing', 18, 2)->nullable();
            $table->decimal('actual_closing', 18, 2)->nullable();
            $table->decimal('variance', 18, 2)->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamps();
            $table->index(['cash_register_id', 'closed_at']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->string('direction', 8);
            $table->decimal('amount', 18, 2);
            $table->string('reference')->nullable();
            $table->string('justification')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['cash_session_id', 'occurred_at']);
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_movement_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidiary_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status', 24)->default('pending');
            $table->date('order_date');
            $table->date('requested_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('preparation_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24);
            $table->decimal('quantity', 18, 3);
            $table->decimal('quantity_before', 18, 3);
            $table->decimal('quantity_after', 18, 3);
            $table->nullableMorphs('source');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['product_id', 'occurred_at']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidiary_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('fingerprint')->unique();
            $table->string('title');
            $table->text('message');
            $table->boolean('active')->default(true);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['subsidiary_id', 'active', 'type']);
        });

        Schema::create('accounting_journals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidiary_id')->constrained()->restrictOnDelete();
            $table->foreignId('accounting_journal_id')->constrained()->restrictOnDelete();
            $table->date('entry_date');
            $table->string('reference');
            $table->string('label');
            $table->nullableMorphs('source');
            $table->boolean('posted')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['subsidiary_id', 'entry_date']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_account_id')->constrained()->restrictOnDelete();
            $table->string('third_party')->nullable();
            $table->string('label')->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subsidiary_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['created_at', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_journals');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('proforma_lines');
        Schema::dropIfExists('proformas');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('products');
        Schema::dropIfExists('accounting_accounts');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('subsidiary_user');
        Schema::dropIfExists('users');
        Schema::dropIfExists('subsidiaries');
        Schema::dropIfExists('group_profiles');
    }
};
