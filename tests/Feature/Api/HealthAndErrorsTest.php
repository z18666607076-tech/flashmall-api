<?php

use App\Models\User;

it('reports api health', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok');
});

it('returns a consistent not found payload', function () {
    $this->getJson('/api/v1/products/999999')
        ->assertNotFound()
        ->assertExactJson([
            'message' => 'Resource not found.',
        ]);
});

it('returns a consistent unauthenticated payload', function () {
    $this->postJson('/api/v1/categories', ['name' => 'Audio'])
        ->assertUnauthorized()
        ->assertExactJson([
            'message' => 'Unauthenticated.',
        ]);
});

it('returns validation errors as json', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/products', [])
        ->assertUnprocessable()
        ->assertJsonStructure([
            'message',
            'errors' => ['title', 'category_id', 'status'],
        ]);
});
