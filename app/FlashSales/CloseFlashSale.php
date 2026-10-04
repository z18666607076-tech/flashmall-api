<?php

namespace App\FlashSales;

use App\Catalog\ProductCache;
use App\Enums\FlashSaleStatus;
use App\Models\FlashSale;
use App\Orders\CancelOrder;
use Illuminate\Support\Facades\DB;

class CloseFlashSale
{
    public function __construct(
        private FlashSaleInventory $inventory,
        private CancelOrder $cancelOrder,
        private ProductCache $cache,
    ) {}

    public function execute(FlashSale $sale, FlashSaleStatus $final): FlashSale
    {
        $closed = DB::transaction(function () use ($sale, $final): FlashSale {
            $locked = FlashSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [FlashSaleStatus::Ended, FlashSaleStatus::Cancelled], true)) {
                return $locked->load('sku');
            }

            $remaining = $this->inventory->takeRemaining((int) $locked->id);
            $this->cancelOrder->returnHeldStock($locked, $remaining);
            $locked->status = $final;
            $locked->save();

            return $locked->load('sku');
        });

        $this->cache->flush();

        return $closed;
    }
}
