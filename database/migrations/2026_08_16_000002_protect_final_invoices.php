<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared("CREATE TRIGGER ks_invoices_no_update BEFORE UPDATE ON invoices FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Une facture définitive est immuable et ne peut pas être modifiée.'");
        DB::unprepared("CREATE TRIGGER ks_invoices_no_delete BEFORE DELETE ON invoices FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Une facture définitive ne peut pas être supprimée.'");
        DB::unprepared("CREATE TRIGGER ks_invoice_lines_no_update BEFORE UPDATE ON invoice_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Les lignes d une facture définitive sont immuables.'");
        DB::unprepared("CREATE TRIGGER ks_invoice_lines_no_delete BEFORE DELETE ON invoice_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Les lignes d une facture définitive ne peuvent pas être supprimées.'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS ks_invoice_lines_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS ks_invoice_lines_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS ks_invoices_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS ks_invoices_no_update');
    }
};
