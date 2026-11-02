<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

class CheckStockAlerts extends Command
{
    protected $signature = 'stock:check-alerts';
    protected $description = 'Actualise les alertes de stock faible et de péremption.';

    public function handle(AlertService $alerts): int
    {
        $count = $alerts->refreshAll();
        $this->info("Alertes recalculées pour {$count} produit(s).");
        return self::SUCCESS;
    }
}
