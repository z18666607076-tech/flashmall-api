<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (Category::query()->exists()) {
            return;
        }

        $electronics = $this->category('Electronics', 'electronics', 1);
        $audio = $this->category('Audio', 'audio', 1, $electronics);
        $apparel = $this->category('Apparel', 'apparel', 2);
        $home = $this->category('Home', 'home', 3);

        $this->product($audio, 'Wireless Earbuds', 'wireless-earbuds', 'Noise-cancelling earbuds with a charging case.', [
            ['attrs' => ['color' => 'black'], 'price_cents' => 39900, 'stock' => 80],
            ['attrs' => ['color' => 'white'], 'price_cents' => 39900, 'stock' => 40],
        ]);

        $this->product($electronics, 'USB-C Cable', 'usb-c-cable', 'Braided 1 metre USB-C cable.', [
            ['attrs' => ['length' => '1m'], 'price_cents' => 2900, 'stock' => 200],
        ]);

        $this->product($apparel, 'Canvas Tote', 'canvas-tote', 'Heavyweight cotton tote for everyday carry.', [
            ['attrs' => ['color' => 'natural'], 'price_cents' => 4900, 'stock' => 60],
            ['attrs' => ['color' => 'navy'], 'price_cents' => 4900, 'stock' => 35],
        ]);

        $this->product($home, 'Ceramic Mug', 'ceramic-mug', '350ml stoneware mug.', [
            ['attrs' => ['color' => 'sand'], 'price_cents' => 3200, 'stock' => 120],
        ]);
    }

    private function category(string $name, string $slug, int $sort, ?Category $parent = null): Category
    {
        return Category::query()->create([
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => $slug,
            'sort' => $sort,
        ]);
    }

    /**
     * @param  list<array{attrs: array<string, string>, price_cents: int, stock: int}>  $skus
     */
    private function product(Category $category, string $title, string $slug, string $description, array $skus): void
    {
        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'status' => ProductStatus::Published,
        ]);

        foreach ($skus as $sku) {
            $product->skus()->create([
                'attrs' => $sku['attrs'],
                'price_cents' => $sku['price_cents'],
                'currency' => 'CNY',
                'stock' => $sku['stock'],
                'version' => 0,
            ]);
        }
    }
}
