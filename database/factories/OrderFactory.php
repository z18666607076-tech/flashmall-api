<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_no' => 'FM'.Str::ulid(),
            'user_id' => User::factory(),
            'status' => OrderStatus::PendingPayment,
            'total_cents' => 1000,
            'currency' => 'CNY',
            'payment_channel' => null,
            'idempotency_key' => null,
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
