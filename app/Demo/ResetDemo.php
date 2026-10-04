<?php

namespace App\Demo;

use App\Catalog\ProductCache;
use App\FlashSales\FlashSaleInventory;
use App\Models\FlashSale;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ResetDemo
{
    public function __construct(
        private FlashSaleInventory $inventory,
        private ProductCache $cache,
    ) {}

    public function execute(): void
    {
        if (config('queue.default') === 'redis') {
            Artisan::call('queue:clear', ['--queue' => 'orders']);
            Artisan::call('queue:clear', ['--queue' => 'default']);
        }

        foreach (FlashSale::query()->pluck('id') as $saleId) {
            $this->inventory->forget((int) $saleId);
        }

        DB::transaction(function (): void {
            DB::table('payment_events')->delete();
            DB::table('flash_sale_orders')->delete();
            DB::table('order_items')->delete();
            DB::table('orders')->delete();
            DB::table('cart_items')->delete();
            DB::table('carts')->delete();
            DB::table('flash_sales')->delete();
            DB::table('skus')->delete();
            DB::table('products')->delete();
            DB::table('categories')->delete();
            DB::table('personal_access_tokens')->delete();
            DB::table('jobs')->delete();
            DB::table('job_batches')->delete();
            DB::table('failed_jobs')->delete();

            User::query()->where('is_admin', false)->delete();
        });

        $this->cache->flush();

        app(CatalogSeeder::class)->run();
        DemoUser::ensure();
    }
}
