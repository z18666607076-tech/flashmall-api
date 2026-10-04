<?php

use App\Enums\OrderStatus;
use App\Jobs\CancelUnpaidOrder;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use App\Orders\CancelOrder;
use App\Orders\TransitionOrder;
use Illuminate\Support\Facades\Queue;

it('checks out a cart with a price snapshot and an idempotency key', function () {
    Queue::fake();

    $user = User::factory()->create();
    $sku = Sku::factory()->create(['stock' => 5, 'price_cents' => 4200, 'currency' => 'CNY']);
    $sku->product->update(['title' => 'Canvas Tote']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/items', ['sku_id' => $sku->id, 'quantity' => 2])
        ->assertOk();

    $created = $this->actingAs($user, 'sanctum')
        ->withHeader('Idempotency-Key', 'checkout-1')
        ->postJson('/api/v1/orders')
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.total_cents', 8400)
        ->assertJsonPath('data.currency', 'CNY')
        ->assertJsonPath('data.items.0.unit_price_cents', 4200)
        ->assertJsonPath('data.items.0.snapshot.product_title', 'Canvas Tote');

    expect($sku->refresh()->stock)->toBe(3)
        ->and($sku->version)->toBe(1);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.item_count', 0);

    $this->actingAs($user, 'sanctum')
        ->withHeader('Idempotency-Key', 'checkout-1')
        ->postJson('/api/v1/cart/items', ['sku_id' => $sku->id, 'quantity' => 1])
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->withHeader('Idempotency-Key', 'checkout-1')
        ->postJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonPath('data.id', $created->json('data.id'));

    expect($sku->refresh()->stock)->toBe(3)
        ->and($user->orders()->count())->toBe(1);

    Queue::assertPushed(CancelUnpaidOrder::class, function (CancelUnpaidOrder $job): bool {
        return $job->queue === 'orders' || $job->delay !== null;
    });
});

it('refuses an empty cart and a line the warehouse cannot fill', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Your cart is empty.');

    $sku = Sku::factory()->create(['stock' => 1, 'price_cents' => 100]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/items', ['sku_id' => $sku->id, 'quantity' => 1])
        ->assertOk();

    $sku->update(['stock' => 0]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders')
        ->assertStatus(409)
        ->assertJsonPath('message', 'Not enough stock for SKU '.$sku->id.'.');

    expect($sku->refresh()->stock)->toBe(0);
});

it('cancels an unpaid order from the delayed job and restores stock', function () {
    $user = User::factory()->create();
    $sku = Sku::factory()->create(['stock' => 4, 'price_cents' => 900]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/items', ['sku_id' => $sku->id, 'quantity' => 2])
        ->assertOk();

    $created = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders')
        ->assertCreated();

    expect($sku->refresh()->stock)->toBe(2);

    $orderId = (int) $created->json('data.id');

    (new CancelUnpaidOrder($orderId))->handle(app(CancelOrder::class));

    expect($sku->refresh()->stock)->toBe(2);

    $this->travel(16)->minutes();

    (new CancelUnpaidOrder($orderId))->handle(app(CancelOrder::class));

    expect($sku->refresh()->stock)->toBe(4);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/orders/'.$orderId)
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

    (new CancelUnpaidOrder($orderId))->handle(app(CancelOrder::class));

    expect($sku->refresh()->stock)->toBe(4);
});

it('moves a paid order through shipment and refunds stock only before it ships', function () {
    $customer = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $sku = Sku::factory()->create(['stock' => 6, 'price_cents' => 1500]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/cart/items', ['sku_id' => $sku->id, 'quantity' => 1])
        ->assertOk();

    $orderId = (int) $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/orders')
        ->assertCreated()
        ->json('data.id');

    $order = Order::query()->findOrFail($orderId);

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/v1/orders/{$orderId}/ship")
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/orders/{$orderId}/ship")
        ->assertStatus(409);

    app(TransitionOrder::class)->markPaid($order, 'stripe');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/orders/{$orderId}/ship")
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped')
        ->assertJsonPath('data.payment_channel', 'stripe');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/orders/{$orderId}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/orders/{$orderId}/refund")
        ->assertStatus(409);

    expect($sku->refresh()->stock)->toBe(5);
});

it('restocks a paid order that is refunded before shipment', function () {
    $customer = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $sku = Sku::factory()->create(['stock' => 3, 'price_cents' => 700]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/cart/items', ['sku_id' => $sku->id, 'quantity' => 2])
        ->assertOk();

    $orderId = (int) $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/orders')
        ->assertCreated()
        ->json('data.id');

    app(TransitionOrder::class)->markPaid(Order::query()->findOrFail($orderId));

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/orders/{$orderId}/refund")
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded');

    expect($sku->refresh()->stock)->toBe(3);

    $this->travel(20)->minutes();
    (new CancelUnpaidOrder($orderId))->handle(app(CancelOrder::class));

    expect($sku->refresh()->stock)->toBe(3);
});

it('lets the buyer cancel a pending order', function () {
    $user = User::factory()->create();
    $sku = Sku::factory()->create(['stock' => 2, 'price_cents' => 300]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/items', ['sku_id' => $sku->id, 'quantity' => 1])
        ->assertOk();

    $orderId = (int) $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/orders')
        ->json('data.id');

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/orders/{$orderId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($sku->refresh()->stock)->toBe(2);
});
