<?php

namespace App\Orders;

use App\Catalog\ProductCache;
use App\Enums\FlashSaleStatus;
use App\Enums\OrderStatus;
use App\Exceptions\CommerceException;
use App\FlashSales\FlashSaleInventory;
use App\Models\FlashSale;
use App\Models\FlashSaleOrder;
use App\Models\Order;
use App\Models\Sku;
use Illuminate\Support\Facades\DB;

class CancelOrder
{
    public function __construct(
        private ProductCache $cache,
        private FlashSaleInventory $inventory,
    ) {}

    public function execute(Order $order): Order
    {
        $cancelled = DB::transaction(function () use ($order): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === OrderStatus::Cancelled) {
                return $locked->load('items');
            }

            if ($locked->status !== OrderStatus::PendingPayment) {
                throw new CommerceException('This order cannot be cancelled.', 409);
            }

            $this->restore($locked);
            $locked->status = OrderStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->save();

            return $locked->load('items');
        });

        $this->cache->flush();

        return $cancelled;
    }

    public function restore(Order $order): void
    {
        $reservation = FlashSaleOrder::query()->where('order_id', $order->id)->lockForUpdate()->first();

        if ($reservation !== null) {
            $sale = FlashSale::query()->whereKey($reservation->flash_sale_id)->lockForUpdate()->first();

            if ($sale !== null && $sale->isOpen()) {
                $this->inventory->release((int) $sale->id, (int) $order->user_id);
            } else {
                $this->restoreSkuStock($order);
            }

            if ($sale !== null) {
                $sale->sold_count = max(0, $sale->sold_count - $reservation->quantity);
                $sale->save();
            }

            $reservation->delete();

            return;
        }

        $this->restoreSkuStock($order);
    }

    public function restoreSkuStock(Order $order): void
    {
        $items = $order->items()->get();
        $ids = $items->pluck('sku_id')->filter()->map(fn ($id): int => (int) $id)->unique()->sort()->values();
        $skus = Sku::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($items as $item) {
            if ($item->sku_id === null) {
                continue;
            }

            $sku = $skus->get($item->sku_id);

            if (! $sku instanceof Sku) {
                continue;
            }

            $sku->stock += $item->quantity;
            $sku->save();
        }
    }

    public function returnHeldStock(FlashSale $sale, int $quantity): void
    {
        if ($quantity < 1 || in_array($sale->status, [FlashSaleStatus::Ended, FlashSaleStatus::Cancelled], true)) {
            return;
        }

        $sku = Sku::query()->whereKey($sale->sku_id)->lockForUpdate()->first();

        if ($sku === null) {
            return;
        }

        $sku->stock += $quantity;
        $sku->save();
    }
}
