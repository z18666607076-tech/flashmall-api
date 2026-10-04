<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Orders\CancelOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[Tries(3)]
#[Backoff(5, 15)]
class CancelUnpaidOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public function handle(CancelOrder $cancel): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order === null || $order->status !== OrderStatus::PendingPayment) {
            return;
        }

        if ($order->expires_at !== null && $order->expires_at->isFuture()) {
            return;
        }

        $cancel->execute($order);
    }
}
