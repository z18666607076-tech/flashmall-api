<?php

namespace App\Orders;

use App\Models\Order;

final readonly class PlaceOrderResult
{
    public function __construct(
        public Order $order,
        public bool $replayed,
    ) {}
}
