<?php

namespace App\Jobs;

use App\Enums\FlashSaleStatus;
use App\FlashSales\CloseFlashSale as CloseFlashSaleAction;
use App\Models\FlashSale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[Tries(3)]
#[Backoff(5, 15)]
class CloseFlashSale implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $saleId) {}

    public function handle(CloseFlashSaleAction $close): void
    {
        $sale = FlashSale::query()->find($this->saleId);

        if ($sale === null || $sale->ends_at->isFuture()) {
            return;
        }

        $close->execute($sale, FlashSaleStatus::Ended);
    }
}
