<?php

namespace App\Console\Commands;

use App\Demo\DemoMode;
use App\Demo\ResetDemo;
use Illuminate\Console\Command;

class ResetDemoCommand extends Command
{
    protected $signature = 'demo:reset {--force : Reset without a confirmation prompt}';

    protected $description = 'Restore the seeded demo catalog, flash sale, and demo user';

    public function handle(ResetDemo $reset): int
    {
        if (! DemoMode::enabled()) {
            $this->error('Demo mode is off. Refusing to reset the database.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Replace demo orders, carts, and catalog data with a fresh seed?')) {
            return self::SUCCESS;
        }

        $reset->execute();
        $this->info('Demo catalog, flash sale, and demo user were restored.');

        return self::SUCCESS;
    }
}
