<?php

namespace App\Http\Controllers\Api\V1;

use App\Cart\CartService;
use App\Exceptions\CommerceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group('Cart', weight: 3)]
class CartController extends Controller
{
    public function show(Request $request, CartService $carts): CartResource
    {
        return CartResource::make($carts->show($this->user($request)));
    }

    public function store(AddCartItemRequest $request, CartService $carts): CartResource
    {
        return CartResource::make($carts->add(
            $this->user($request),
            $request->integer('sku_id'),
            $request->integer('quantity'),
        ));
    }

    public function update(UpdateCartItemRequest $request, CartItem $item, CartService $carts): CartResource
    {
        return CartResource::make($carts->update(
            $this->user($request),
            $item,
            $request->integer('quantity'),
        ));
    }

    public function destroy(Request $request, CartItem $item, CartService $carts): CartResource
    {
        return CartResource::make($carts->remove($this->user($request), $item));
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new CommerceException('Unauthenticated.', 401);
        }

        return $user;
    }
}
