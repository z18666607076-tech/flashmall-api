<?php

use App\Demo\DemoMode;
use App\Demo\DemoUser;
use App\FlashSales\FlashSaleInventory;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Payments\FakeStripeGateway;
use App\Payments\PaymentGateways;
use Database\Seeders\DatabaseSeeder;

it('rejects catalog writes and keeps checkout available in demo mode', function () {
    config(['demo.enabled' => true]);
    $this->seed(DatabaseSeeder::class);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/categories', [
            'name' => 'Blocked',
            'slug' => 'blocked',
            'sort' => 1,
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'The public demo does not allow this change.');

    $login = $this->postJson('/api/v1/auth/wechat', [
        'code' => 'demo',
        'name' => 'Someone Else',
    ])->assertOk()
        ->assertJsonPath('user.name', 'Demo Shopper');

    $token = $login->json('token');
    $skuId = Product::query()->where('slug', 'usb-c-cable')->firstOrFail()->skus()->value('id');

    $this->withToken($token)
        ->postJson('/api/v1/cart/items', ['sku_id' => $skuId, 'quantity' => 1])
        ->assertOk();

    $this->withToken($token)
        ->postJson('/api/v1/orders')
        ->assertCreated();
});

it('forces the fake wechat and payment drivers while demo mode is on', function () {
    config([
        'demo.enabled' => true,
        'payments.driver' => 'http',
        'wechat.driver' => 'http',
    ]);

    DemoMode::apply();

    expect(config('wechat.driver'))->toBe('fake')
        ->and(app(PaymentGateways::class)->get('stripe'))->toBeInstanceOf(FakeStripeGateway::class);
});

it('refuses to reset the database when demo mode is off', function () {
    config(['demo.enabled' => false]);

    $this->artisan('demo:reset', ['--force' => true])->assertFailed();
});

it('resets demo orders and restores the seeded catalog and flash sale', function () {
    config(['demo.enabled' => true]);
    $this->seed(DatabaseSeeder::class);

    $admin = User::factory()->admin()->create();
    Order::factory()->create();
    $saleId = (int) FlashSale::query()->value('id');

    $this->artisan('demo:reset', ['--force' => true])->assertSuccessful();

    $sale = FlashSale::query()->firstOrFail();

    expect(Order::query()->count())->toBe(0)
        ->and(User::query()->whereKey($admin->id)->exists())->toBeTrue()
        ->and(User::query()->where('wechat_openid', DemoUser::openid())->exists())->toBeTrue()
        ->and(Product::query()->where('slug', 'wireless-earbuds')->exists())->toBeTrue()
        ->and(FlashSale::query()->count())->toBe(1)
        ->and(app(FlashSaleInventory::class)->remaining((int) $sale->id))->toBe(10)
        ->and((int) $sale->id)->not->toBe($saleId);
});
