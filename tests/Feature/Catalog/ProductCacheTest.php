<?php

use App\Models\Product;
use App\Models\User;

it('serves a published product from cache until a write flushes it', function () {
    $user = User::factory()->admin()->create();
    $product = Product::factory()->create(['title' => 'Cached title']);

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Cached title');

    Product::query()->whereKey($product->id)->update(['title' => 'Changed in the database']);

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Cached title');

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/products/{$product->id}", [
            'category_id' => $product->category_id,
            'title' => 'Updated title',
            'description' => $product->description,
            'status' => 'published',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated title');

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated title');
});

it('serves the product index from cache until a write flushes it', function () {
    $user = User::factory()->admin()->create();
    $product = Product::factory()->create(['title' => 'Listed title']);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Listed title');

    Product::query()->whereKey($product->id)->update(['title' => 'Changed in the database']);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Listed title');

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/products/{$product->id}", [
            'category_id' => $product->category_id,
            'title' => 'Fresh title',
            'description' => $product->description,
            'status' => 'published',
        ])
        ->assertOk();

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Fresh title');
});
