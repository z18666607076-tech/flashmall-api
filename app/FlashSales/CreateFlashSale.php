<?php

namespace App\FlashSales;

use App\Catalog\ProductCache;
use App\Enums\FlashSaleStatus;
use App\Enums\ProductStatus;
use App\Exceptions\CommerceException;
use App\Jobs\CloseFlashSale as CloseFlashSaleJob;
use App\Models\FlashSale;
use App\Models\Sku;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateFlashSale
{
    public function __construct(
        private FlashSaleInventory $inventory,
        private ProductCache $cache,
    ) {}

    public function execute(
        int $skuId,
        string $title,
        int $priceCents,
        Carbon $startsAt,
        Carbon $endsAt,
        int $totalStock,
        int $perUserLimit,
    ): FlashSale {
        $sale = DB::transaction(function () use ($skuId, $title, $priceCents, $startsAt, $endsAt, $totalStock, $perUserLimit): FlashSale {
            if ($endsAt->lte($startsAt) || $endsAt->isPast()) {
                throw new CommerceException('The flash sale end time must be in the future.');
            }

            if ($perUserLimit > $totalStock) {
                throw new CommerceException('The per-user limit cannot exceed the flash sale stock.');
            }

            $sku = Sku::query()->with('product')->whereKey($skuId)->lockForUpdate()->first();

            if ($sku === null || $sku->product === null || $sku->product->status !== ProductStatus::Published) {
                throw new CommerceException('This SKU is not available.');
            }

            if ($sku->stock < $totalStock) {
                throw new CommerceException('Not enough SKU stock to hold for this flash sale.', 409);
            }

            $sku->stock -= $totalStock;
            $sku->save();

            $sale = FlashSale::query()->create([
                'sku_id' => $sku->id,
                'title' => $title,
                'price_cents' => $priceCents,
                'currency' => $sku->currency,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'total_stock' => $totalStock,
                'per_user_limit' => $perUserLimit,
                'sold_count' => 0,
                'status' => now()->gte($startsAt) ? FlashSaleStatus::Active : FlashSaleStatus::Scheduled,
            ]);

            $ttl = max(60, (int) now()->diffInSeconds($endsAt) + 86400);
            $this->inventory->seed((int) $sale->id, $totalStock, $ttl);

            $saleId = (int) $sale->id;

            DB::afterCommit(function () use ($saleId, $endsAt): void {
                CloseFlashSaleJob::dispatch($saleId)->delay($endsAt);
            });

            return $sale->load('sku.product');
        });

        $this->cache->flush();

        return $sale;
    }
}
