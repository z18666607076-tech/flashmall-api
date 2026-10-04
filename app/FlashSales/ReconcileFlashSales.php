<?php

namespace App\FlashSales;

use App\Enums\FlashSaleStatus;
use App\Models\FlashSale;
use App\Models\FlashSaleOrder;

class ReconcileFlashSales
{
    public function __construct(
        private MaterializeFlashSaleOrder $materialize,
        private CloseFlashSale $close,
    ) {}

    public function execute(): int
    {
        $materialized = 0;

        FlashSaleOrder::query()
            ->whereNull('order_id')
            ->orderBy('id')
            ->each(function (FlashSaleOrder $reservation) use (&$materialized): void {
                $order = $this->materialize->execute((int) $reservation->id);

                if ($order !== null) {
                    $materialized++;
                }
            });

        FlashSale::query()
            ->whereIn('status', [FlashSaleStatus::Scheduled, FlashSaleStatus::Active])
            ->where('ends_at', '<=', now())
            ->orderBy('id')
            ->each(function (FlashSale $sale): void {
                $this->close->execute($sale, FlashSaleStatus::Ended);
            });

        return $materialized;
    }
}
