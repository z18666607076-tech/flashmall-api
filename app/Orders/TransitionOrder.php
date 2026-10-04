<?php

namespace App\Orders;

use App\Catalog\ProductCache;
use App\Enums\OrderStatus;
use App\Exceptions\CommerceException;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class TransitionOrder
{
    public function __construct(
        private CancelOrder $cancelOrder,
        private ProductCache $cache,
    ) {}

    public function markPaid(Order $order, ?string $channel = null): Order
    {
        return $this->move($order, OrderStatus::Paid, function (Order $locked) use ($channel): void {
            $locked->paid_at = now();
            $locked->payment_channel = $channel;
        });
    }

    public function ship(Order $order): Order
    {
        return $this->move($order, OrderStatus::Shipped, function (Order $locked): void {
            $locked->shipped_at = now();
        });
    }

    public function complete(Order $order): Order
    {
        return $this->move($order, OrderStatus::Completed, function (Order $locked): void {
            $locked->completed_at = now();
        });
    }

    public function refund(Order $order): Order
    {
        $refunded = DB::transaction(function () use ($order): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->guard($locked, OrderStatus::Refunded);

            if ($locked->status === OrderStatus::Paid) {
                $this->cancelOrder->restore($locked);
            }

            $locked->status = OrderStatus::Refunded;
            $locked->refunded_at = now();
            $locked->save();

            return $locked->load('items');
        });

        $this->cache->flush();

        return $refunded;
    }

    /**
     * @param  callable(Order): void  $fill
     */
    private function move(Order $order, OrderStatus $target, callable $fill): Order
    {
        return DB::transaction(function () use ($order, $target, $fill): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->guard($locked, $target);
            $fill($locked);
            $locked->status = $target;
            $locked->save();

            return $locked->load('items');
        });
    }

    private function guard(Order $order, OrderStatus $target): void
    {
        if (! $order->status->canTransitionTo($target)) {
            throw new CommerceException(
                'Cannot move an order from '.$order->status->value.' to '.$target->value.'.',
                409,
            );
        }
    }
}
