<?php

namespace App\Jobs;

use App\FlashSales\AbandonFlashSaleReservation;
use App\FlashSales\MaterializeFlashSaleOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

#[Tries(3)]
#[Backoff(5, 15)]
class CreateFlashSaleOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $reservationId) {}

    public function handle(MaterializeFlashSaleOrder $materialize): void
    {
        $materialize->execute($this->reservationId);
    }

    public function failed(?Throwable $exception): void
    {
        app(AbandonFlashSaleReservation::class)->execute($this->reservationId);
    }
}
