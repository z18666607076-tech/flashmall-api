<?php

namespace App\FlashSales;

use App\Enums\OrderStatus;
use App\Jobs\CancelUnpaidOrder;
use App\Models\FlashSale;
use App\Models\FlashSaleOrder;
use App\Models\Order;
use App\Models\Sku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaterializeFlashSaleOrder
{
    public function execute(int $reservationId): ?Order
    {
        return DB::transaction(function () use ($reservationId): ?Order {
            $reservation = FlashSaleOrder::query()->whereKey($reservationId)->lockForUpdate()->first();

            if ($reservation === null) {
                return null;
            }

            if ($reservation->order_id !== null) {
                return $reservation->order()->first();
            }

            $sale = FlashSale::query()->with('sku.product')->whereKey($reservation->flash_sale_id)->lockForUpdate()->firstOrFail();
            $sku = Sku::query()->find($sale->sku_id);

            $order = Order::query()->create([
                'order_no' => 'FM'.Str::ulid(),
                'user_id' => $reservation->user_id,
                'status' => OrderStatus::PendingPayment,
                'total_cents' => $sale->price_cents * $reservation->quantity,
                'currency' => $sale->currency,
                'expires_at' => now()->addSeconds((int) config('orders.unpaid_ttl_seconds')),
            ]);

            $order->items()->create([
                'sku_id' => $sku?->id,
                'quantity' => $reservation->quantity,
                'unit_price_cents' => $sale->price_cents,
                'currency' => $sale->currency,
                'snapshot' => [
                    'product_id' => $sku?->product_id,
                    'product_title' => $sku?->product?->title,
                    'attrs' => $sku?->attrs,
                    'flash_sale_id' => $sale->id,
                    'flash_sale_title' => $sale->title,
                ],
            ]);

            $sale->sold_count += $reservation->quantity;
            $sale->save();
            $reservation->order_id = $order->id;
            $reservation->save();

            $orderId = (int) $order->id;
            $expiresAt = $order->expires_at;

            DB::afterCommit(function () use ($orderId, $expiresAt): void {
                if ($expiresAt !== null) {
                    CancelUnpaidOrder::dispatch($orderId)->delay($expiresAt);
                }
            });

            return $order->load('items');
        });
    }
}
