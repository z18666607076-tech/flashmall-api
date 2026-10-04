<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

it('lists categories without authentication', function () {
    $parent = Category::factory()->create(['name' => 'Electronics', 'sort' => 2]);
    Category::factory()->create(['name' => 'Audio', 'sort' => 1, 'parent_id' => $parent->id]);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Audio')
        ->assertJsonPath('data.1.name', 'Electronics');
});

it('creates updates and deletes a category', function () {
    $user = User::factory()->create();
    $parent = Category::factory()->create();

    $created = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/categories', [
            'name' => 'Home',
            'slug' => 'home',
            'parent_id' => $parent->id,
            'sort' => 3,
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'home')
        ->assertJsonPath('data.parent_id', $parent->id);

    $id = $created->json('data.id');

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/categories/{$id}", [
            'name' => 'Home & Living',
            'parent_id' => null,
            'sort' => 4,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Home & Living')
        ->assertJsonPath('data.parent_id', null);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/categories/{$id}")
        ->assertNoContent();

    $this->getJson("/api/v1/categories/{$id}")->assertNotFound();
});

it('refuses to delete a category that still has products or children', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/categories/{$category->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Category still has products or child categories.');
});

it('rejects a category nested under its own descendant', function () {
    $user = User::factory()->create();
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/categories/{$parent->id}", [
            'name' => $parent->name,
            'parent_id' => $child->id,
            'sort' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});
