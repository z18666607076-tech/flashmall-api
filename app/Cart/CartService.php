<?php

namespace App\Cart;

use App\Enums\ProductStatus;
use App\Exceptions\CommerceException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function show(User $user): Cart
    {
        return $this->cartFor($user)->load('items.sku.product');
    }

    public function add(User $user, int $skuId, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $skuId, $quantity): Cart {
            $cart = $this->lock($user);
            $sku = $this->availableSku($skuId);
            $this->assertCurrency($cart, $sku);

            $item = $cart->items()->where('sku_id', $sku->id)->first();
            $next = ($item === null ? 0 : $item->quantity) + $quantity;
            $this->assertStock($sku, $next);

            if ($item === null) {
                $cart->items()->create([
                    'sku_id' => $sku->id,
                    'quantity' => $next,
                ]);
            } else {
                $item->quantity = $next;
                $item->save();
            }

            return $cart->load('items.sku.product');
        });
    }

    public function update(User $user, CartItem $item, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $item, $quantity): Cart {
            $cart = $this->lock($user);
            $owned = $cart->items()->whereKey($item->id)->first();

            if ($owned === null) {
                throw new CommerceException('Cart item not found.', 404);
            }

            $sku = $this->availableSku((int) $owned->sku_id);
            $this->assertStock($sku, $quantity);
            $owned->quantity = $quantity;
            $owned->save();

            return $cart->load('items.sku.product');
        });
    }

    public function remove(User $user, CartItem $item): Cart
    {
        return DB::transaction(function () use ($user, $item): Cart {
            $cart = $this->lock($user);
            $cart->items()->whereKey($item->id)->delete();

            return $cart->load('items.sku.product');
        });
    }

    private function cartFor(User $user): Cart
    {
        try {
            return Cart::query()->firstOrCreate(['user_id' => $user->id]);
        } catch (UniqueConstraintViolationException) {
            return Cart::query()->where('user_id', $user->id)->firstOrFail();
        }
    }

    private function lock(User $user): Cart
    {
        $cart = $this->cartFor($user);

        return Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
    }

    private function availableSku(int $skuId): Sku
    {
        $sku = Sku::query()->with('product')->find($skuId);

        if ($sku === null || $sku->product === null || $sku->product->status !== ProductStatus::Published) {
            throw new CommerceException('This SKU is not available.');
        }

        return $sku;
    }

    private function assertStock(Sku $sku, int $quantity): void
    {
        if ($quantity > $sku->stock) {
            throw new CommerceException('Quantity exceeds available stock.', 409);
        }
    }

    private function assertCurrency(Cart $cart, Sku $sku): void
    {
        $existing = $cart->items()->with('sku')->first();

        if ($existing !== null && $existing->sku !== null && $existing->sku->currency !== $sku->currency) {
            throw new CommerceException('Cart items must use one currency.');
        }
    }
}
