<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;

it('lists only published products and can filter them', function () {
    $category = Category::factory()->create();
    $other = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id, 'title' => 'Ceramic Mug']);
    Product::factory()->create(['category_id' => $other->id, 'title' => 'Canvas Tote']);
    Product::factory()->draft()->create(['category_id' => $category->id, 'title' => 'Secret Draft']);

    $this->getJson('/api/v1/products?q=mug')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Ceramic Mug');

    $this->getJson('/api/v1/products?category_id='.$category->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Ceramic Mug');
});

it('hides draft products from the public detail endpoint', function () {
    $product = Product::factory()->draft()->create();

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');
});

it('creates a product with a sku and updates stock with an optimistic version', function () {
    $user = User::factory()->admin()->create();
    $category = Category::factory()->create();

    $created = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/products', [
            'category_id' => $category->id,
            'title' => 'USB-C Cable',
            'description' => 'One metre cable.',
            'status' => 'published',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'USB-C Cable')
        ->assertJsonPath('data.slug', 'usb-c-cable')
        ->assertJsonCount(0, 'data.skus');

    $productId = $created->json('data.id');

    $sku = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/products/{$productId}/skus", [
            'attrs' => ['length' => '1m'],
            'price_cents' => 2900,
            'currency' => 'cny',
            'stock' => 10,
        ])
        ->assertCreated()
        ->assertJsonPath('data.currency', 'CNY')
        ->assertJsonPath('data.stock', 10)
        ->assertJsonPath('data.version', 0);

    $skuId = $sku->json('data.id');

    $this->getJson("/api/v1/products/{$productId}")
        ->assertOk()
        ->assertJsonPath('data.skus.0.stock', 10);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/skus/{$skuId}", [
            'attrs' => ['length' => '1m'],
            'price_cents' => 3100,
            'currency' => 'CNY',
            'stock' => 10,
        ])
        ->assertOk()
        ->assertJsonPath('data.version', 0)
        ->assertJsonPath('data.price_cents', 3100);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/skus/{$skuId}", [
            'attrs' => ['length' => '1m'],
            'price_cents' => 3100,
            'currency' => 'CNY',
            'stock' => 9,
        ])
        ->assertOk()
        ->assertJsonPath('data.stock', 9)
        ->assertJsonPath('data.version', 1);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/skus/{$skuId}")
        ->assertNoContent();

    expect(Sku::query()->count())->toBe(0);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/products/{$productId}")
        ->assertNoContent();

    expect(Product::query()->count())->toBe(0);
});

it('seeds a readable catalog', function () {
    $this->seed();

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonCount(4, 'data');

    expect(Category::query()->count())->toBe(4);
});
