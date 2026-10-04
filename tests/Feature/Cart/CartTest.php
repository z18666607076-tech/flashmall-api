<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Sku;
use App\Models\User;

it('requires authentication', function () {
    $this->getJson('/api/v1/cart')->assertUnauthorized();
});

it('adds a sku and refuses quantities above stock', function () {
    $user = User::factory()->create();
    $sku = Sku::factory()->create(['stock' => 2, 'price_cents' => 1800, 'currency' => 'CNY']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/items', ['sku_id' => $sku->id, 'quantity' => 2])
        ->assertOk()
        ->assertJsonPath('data.item_count', 2)
        ->assertJsonPath('data.total_cents', 3600)
        ->assertJsonPath('data.items.0.sku_id', $sku->id);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/items', ['sku_id' => $sku->id, 'quantity' => 1])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Quantity exceeds available stock.');
});

it('rejects a draft product and updates or removes a line', function () {
    $user = User::factory()->create();
    $draft = Product::factory()->draft()->create();
    $hidden = Sku::factory()->create(['product_id' => $draft->id, 'stock' => 5]);
    $sku = Sku::factory()->create(['stock' => 4, 'price_cents' => 500]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/items', ['sku_id' => $hidden->id, 'quantity' => 1])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This SKU is not available.');

    $added = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/items', ['sku_id' => $sku->id, 'quantity' => 1])
        ->assertOk();

    $itemId = $added->json('data.items.0.id');

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 3])
        ->assertOk()
        ->assertJsonPath('data.items.0.quantity', 3);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/cart/items/{$itemId}")
        ->assertOk()
        ->assertJsonPath('data.item_count', 0);

    expect(Product::query()->where('status', ProductStatus::Published)->count())->toBe(1);
});
