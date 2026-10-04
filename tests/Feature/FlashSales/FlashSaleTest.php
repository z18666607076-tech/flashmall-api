<?php

use App\Enums\ReserveOutcome;
use App\FlashSales\AbandonFlashSaleReservation;
use App\FlashSales\CreateFlashSale;
use App\FlashSales\FlashSaleInventory;
use App\FlashSales\ReconcileFlashSales;
use App\Jobs\CreateFlashSaleOrder;
use App\Models\FlashSaleOrder;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Racer;

it('holds sku stock and lets one buyer purchase at the flash price', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create();
    $sku = Sku::factory()->create(['stock' => 20, 'price_cents' => 5000, 'currency' => 'CNY']);

    $this->actingAs($buyer, 'sanctum')
        ->postJson('/api/v1/flash-sales', [
            'sku_id' => $sku->id,
            'title' => 'Morning drop',
            'price_cents' => 1900,
            'starts_at' => now()->subMinute()->toIso8601String(),
            'ends_at' => now()->addHour()->toIso8601String(),
            'total_stock' => 5,
            'per_user_limit' => 1,
        ])
        ->assertForbidden();

    $created = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/flash-sales', [
            'sku_id' => $sku->id,
            'title' => 'Morning drop',
            'price_cents' => 1900,
            'starts_at' => now()->subMinute()->toIso8601String(),
            'ends_at' => now()->addHour()->toIso8601String(),
            'total_stock' => 5,
            'per_user_limit' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.price_cents', 1900);

    expect($sku->refresh()->stock)->toBe(15);

    $saleId = (int) $created->json('data.id');

    $this->getJson('/api/v1/flash-sales')
        ->assertOk()
        ->assertJsonPath('data.0.id', $saleId);

    $purchase = $this->actingAs($buyer, 'sanctum')
        ->postJson("/api/v1/flash-sales/{$saleId}/purchase", ['quantity' => 1])
        ->assertAccepted()
        ->assertJsonPath('data.status', 'ordered')
        ->assertJsonPath('data.quantity', 1);

    $order = Order::query()->findOrFail($purchase->json('data.order_id'));

    expect($order->total_cents)->toBe(1900)
        ->and($order->items()->first()?->unit_price_cents)->toBe(1900)
        ->and($sku->refresh()->stock)->toBe(15)
        ->and(app(FlashSaleInventory::class)->remaining($saleId))->toBe(4);

    $this->actingAs($buyer, 'sanctum')
        ->postJson("/api/v1/flash-sales/{$saleId}/purchase", ['quantity' => 1])
        ->assertStatus(409);
});

it('rejects a closed window and returns held stock when an admin cancels the sale', function () {
    $admin = User::factory()->admin()->create();
    $sku = Sku::factory()->create(['stock' => 8, 'price_cents' => 1000]);

    $sale = app(CreateFlashSale::class)->execute(
        (int) $sku->id,
        'Later',
        500,
        now()->addHour(),
        now()->addHours(2),
        3,
        1,
    );

    expect($sku->refresh()->stock)->toBe(5);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/flash-sales/{$sale->id}/purchase", ['quantity' => 1])
        ->assertStatus(409);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/flash-sales/{$sale->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($sku->refresh()->stock)->toBe(8)
        ->and(app(FlashSaleInventory::class)->remaining((int) $sale->id))->toBe(0);
});

it('reconciles a reservation whose order job has not run', function () {
    Queue::fake();

    $buyer = User::factory()->create();
    $sku = Sku::factory()->create(['stock' => 6, 'price_cents' => 2000]);
    $sale = app(CreateFlashSale::class)->execute(
        (int) $sku->id,
        'Reconcile me',
        800,
        now()->subMinute(),
        now()->addHour(),
        2,
        1,
    );

    $this->actingAs($buyer, 'sanctum')
        ->postJson("/api/v1/flash-sales/{$sale->id}/purchase", ['quantity' => 1])
        ->assertAccepted()
        ->assertJsonPath('data.order_id', null);

    Queue::assertPushedOn('orders', CreateFlashSaleOrder::class);

    expect(Order::query()->count())->toBe(0);

    expect(app(ReconcileFlashSales::class)->execute())->toBe(1)
        ->and(Order::query()->count())->toBe(1)
        ->and(FlashSaleOrder::query()->whereNotNull('order_id')->count())->toBe(1);
});

it('releases a buyer when the order job fails before the order exists', function () {
    $buyer = User::factory()->create();
    $sku = Sku::factory()->create(['stock' => 4, 'price_cents' => 900]);
    $sale = app(CreateFlashSale::class)->execute(
        (int) $sku->id,
        'Abandon',
        400,
        now()->subMinute(),
        now()->addHour(),
        2,
        1,
    );

    $inventory = app(FlashSaleInventory::class);
    expect($inventory->tryReserve((int) $sale->id, (int) $buyer->id, 1, 1))->toBe(ReserveOutcome::Reserved);

    $reservation = FlashSaleOrder::query()->create([
        'flash_sale_id' => $sale->id,
        'user_id' => $buyer->id,
        'quantity' => 1,
    ]);

    app(AbandonFlashSaleReservation::class)->execute((int) $reservation->id);

    expect(FlashSaleOrder::query()->count())->toBe(0)
        ->and($inventory->remaining((int) $sale->id))->toBe(2)
        ->and($inventory->tryReserve((int) $sale->id, (int) $buyer->id, 1, 1))->toBe(ReserveOutcome::Reserved);
});

it('does not oversell when many buyers reserve at once', function () {
    $inventory = app(FlashSaleInventory::class);
    $saleId = 900_001;
    $inventory->seed($saleId, 5, 120);

    $processes = [];

    foreach (range(1, 16) as $userId) {
        $process = Racer::start([
            PHP_BINARY,
            base_path('tests/Support/flash_sale_racer.php'),
            (string) $saleId,
            (string) $userId,
            '1',
            '1',
        ]);
        $process->start();
        $processes[] = $process;
    }

    $reserved = 0;
    $crashed = [];

    foreach ($processes as $process) {
        $process->wait();

        if ($process->getExitCode() === 0) {
            $reserved++;
        } elseif ($process->getExitCode() !== 2) {
            $crashed[] = $process->getErrorOutput().$process->getOutput();
        }
    }

    expect($crashed)->toBe([])
        ->and($reserved)->toBe(5)
        ->and($inventory->remaining($saleId))->toBe(0)
        ->and($inventory->buyerCount($saleId))->toBe(5);
});
