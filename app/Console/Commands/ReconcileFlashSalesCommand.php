<?php

namespace App\Console\Commands;

use App\FlashSales\ReconcileFlashSales;
use Illuminate\Console\Command;

class ReconcileFlashSalesCommand extends Command
{
    protected $signature = 'flash-sales:reconcile';

    protected $description = 'Create missing flash-sale orders and close sales whose window has ended';

    public function handle(ReconcileFlashSales $reconcile): int
    {
        $created = $reconcile->execute();
        $this->info("Materialized {$created} flash-sale order(s).");

        return self::SUCCESS;
    }
}
