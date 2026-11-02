<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $appendOnly = [
        'journal_entries' => 'Une écriture comptable',
        'journal_entry_lines' => 'Une ligne d écriture comptable',
        'cash_movements' => 'Un mouvement de caisse',
        'activity_logs' => 'Une entrée du journal d audit',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->appendOnly as $table => $label) {
            DB::unprepared("CREATE TRIGGER ks_{$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$label} est immuable.'");
            DB::unprepared("CREATE TRIGGER ks_{$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$label} ne peut pas être supprimé(e).'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_keys($this->appendOnly) as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS ks_{$table}_no_delete");
            DB::unprepared("DROP TRIGGER IF EXISTS ks_{$table}_no_update");
        }
    }
};
