<?php

it('publishes browsable openapi documentation', function () {
    $this->get('/docs/api')->assertOk();

    $spec = $this->get('/docs/api.json')->assertOk();

    expect($spec->json('openapi'))->toStartWith('3.');

    $paths = array_keys($spec->json('paths'));

    expect(collect($paths)->contains(fn (string $path): bool => str_contains($path, 'products')))->toBeTrue()
        ->and(collect($paths)->contains(fn (string $path): bool => str_contains($path, 'auth/wechat')))->toBeTrue()
        ->and(collect($paths)->contains(fn (string $path): bool => str_contains($path, 'orders')))->toBeTrue()
        ->and(collect($paths)->contains(fn (string $path): bool => str_contains($path, 'flash-sales')))->toBeTrue()
        ->and(collect($paths)->contains(fn (string $path): bool => str_contains($path, 'orders/{order}/pay')))->toBeTrue()
        ->and(collect($paths)->contains(fn (string $path): bool => str_contains($path, 'payments/stripe/webhook')))->toBeTrue();
});
