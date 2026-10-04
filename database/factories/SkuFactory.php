<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sku>
 */
class SkuFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'attrs' => [
                'color' => fake()->safeColorName(),
                'size' => fake()->randomElement(['S', 'M', 'L']),
            ],
            'price_cents' => fake()->numberBetween(100, 50000),
            'currency' => 'CNY',
            'stock' => fake()->numberBetween(0, 100),
            'version' => 0,
        ];
    }
}
