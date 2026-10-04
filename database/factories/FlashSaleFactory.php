<?php

namespace Database\Factories;

use App\Enums\FlashSaleStatus;
use App\Models\FlashSale;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlashSale>
 */
class FlashSaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku_id' => Sku::factory(),
            'title' => 'Flash sale',
            'price_cents' => 1000,
            'currency' => 'CNY',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'total_stock' => 5,
            'per_user_limit' => 1,
            'sold_count' => 0,
            'status' => FlashSaleStatus::Active,
        ];
    }
}
