<?php

namespace App\Orders;

use App\Catalog\ProductCache;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Exceptions\CommerceException;
use App\Jobs\CancelUnpaidOrder;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlaceOrder
{
    public function __construct(private ProductCache $cache) {}

    public function execute(User $user, ?string $idempotencyKey): PlaceOrderResult
    {
        try {
            $result = DB::transaction(function () use ($user, $idempotencyKey): PlaceOrderResult {
                User::query()->whereKey($user->id)->lockForUpdate()->first();

                if ($idempotencyKey !== null) {
                    $existing = Order::query()
                        ->where('user_id', $user->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($existing !== null) {
                        return new PlaceOrderResult($existing->load('items'), true);
                    }
                }

                $cart = Cart::query()->where('user_id', $user->id)->lockForUpdate()->first();

                if (! $cart instanceof Cart) {
                    throw new CommerceException('Your cart is empty.');
                }

                $lines = $cart->items()->with(['sku.product'])->get();

                if ($lines->isEmpty()) {
                    throw new CommerceException('Your cart is empty.');
                }

                $skuIds = $lines->pluck('sku_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values();
                $skus = Sku::query()->whereIn('id', $skuIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                $currency = null;
                $total = 0;

                foreach ($lines as $line) {
                    $sku = $skus->get($line->sku_id);

                    if (! $sku instanceof Sku || $sku->product === null || $sku->product->status !== ProductStatus::Published) {
                        throw new CommerceException('SKU '.$line->sku_id.' is not available.');
                    }

                    $currency ??= $sku->currency;

                    if ($sku->currency !== $currency) {
                        throw new CommerceException('Cart items must use one currency.');
                    }

                    if ($sku->stock < $line->quantity) {
                        throw new CommerceException('Not enough stock for SKU '.$sku->id.'.', 409);
                    }

                    $total += $sku->price_cents * $line->quantity;
                }

                if (! is_string($currency)) {
                    throw new CommerceException('Your cart is empty.');
                }

                $order = Order::query()->create([
                    'order_no' => 'FM'.Str::ulid(),
                    'user_id' => $user->id,
                    'status' => OrderStatus::PendingPayment,
                    'total_cents' => $total,
                    'currency' => $currency,
                    'idempotency_key' => $idempotencyKey,
                    'expires_at' => now()->addSeconds((int) config('orders.unpaid_ttl_seconds')),
                ]);

                foreach ($lines as $line) {
                    $sku = $skus->get($line->sku_id);

                    if (! $sku instanceof Sku) {
                        throw new CommerceException('SKU '.$line->sku_id.' is not available.');
                    }

                    $order->items()->create([
                        'sku_id' => $sku->id,
                        'quantity' => $line->quantity,
                        'unit_price_cents' => $sku->price_cents,
                        'currency' => $sku->currency,
                        'snapshot' => [
                            'product_id' => $sku->product_id,
                            'product_title' => $sku->product?->title,
                            'attrs' => $sku->attrs,
                        ],
                    ]);

                    $sku->stock -= $line->quantity;
                    $sku->save();
                }

                $cart->items()->delete();

                $orderId = (int) $order->id;
                $expiresAt = $order->expires_at;

                DB::afterCommit(function () use ($orderId, $expiresAt): void {
                    if ($expiresAt !== null) {
                        CancelUnpaidOrder::dispatch($orderId)->delay($expiresAt);
                    }
                });

                return new PlaceOrderResult($order->load('items'), false);
            });
        } catch (UniqueConstraintViolationException) {
            $existing = Order::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing === null) {
                throw new CommerceException('Could not create the order. Retry the request.', 409);
            }

            return new PlaceOrderResult($existing->load('items'), true);
        }

        if (! $result->replayed) {
            $this->cache->flush();
        }

        return $result;
    }
}
