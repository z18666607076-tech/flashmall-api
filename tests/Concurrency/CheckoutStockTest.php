<?php

use App\Cart\CartService;
use App\Models\Order;
use App\Models\Sku;
use App\Models\User;
use Tests\Support\Racer;

it('lets only as many checkouts succeed as the sku has in stock', function () {
    $sku = Sku::factory()->create(['stock' => 3, 'price_cents' => 1100, 'currency' => 'CNY']);
    $users = User::factory()->count(8)->create();

    foreach ($users as $user) {
        app(CartService::class)->add($user, (int) $sku->id, 1);
    }

    $processes = [];

    foreach ($users as $user) {
        $process = Racer::start([
            PHP_BINARY,
            base_path('tests/Support/checkout_racer.php'),
            (string) $user->id,
        ]);
        $process->start();
        $processes[] = $process;
    }

    $succeeded = 0;
    $crashed = [];

    foreach ($processes as $process) {
        $process->wait();

        if ($process->getExitCode() === 0) {
            $succeeded++;
        } elseif ($process->getExitCode() !== 2) {
            $crashed[] = trim($process->getErrorOutput()."\n".$process->getOutput());
        }
    }

    expect($crashed)->toBe([])
        ->and($succeeded)->toBe(3)
        ->and($sku->refresh()->stock)->toBe(0)
        ->and(Order::query()->count())->toBe(3);
});
