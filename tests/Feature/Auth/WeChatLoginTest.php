<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('issues a sanctum token for a wechat login code', function () {
    Http::fake();

    $response = $this->postJson('/api/v1/auth/wechat', [
        'code' => 'demo-user',
        'name' => 'Ada',
    ]);

    $response->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.name', 'Ada')
        ->assertJsonStructure(['token', 'user' => ['id', 'wechat_openid', 'wechat_unionid']]);

    Http::assertNothingSent();

    $token = $response->json('token');

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.name', 'Ada')
        ->assertJsonPath('data.wechat_openid', $response->json('user.wechat_openid'));
});

it('logs the same mini program user in again', function () {
    $first = $this->postJson('/api/v1/auth/wechat', ['code' => 'same-code'])->assertOk();
    $second = $this->postJson('/api/v1/auth/wechat', ['code' => 'same-code'])->assertOk();

    expect($first->json('user.id'))->toBe($second->json('user.id'))
        ->and($first->json('token'))->not->toBe($second->json('token'))
        ->and(User::query()->count())->toBe(1);
});

it('creates distinct users for distinct codes', function () {
    $this->postJson('/api/v1/auth/wechat', ['code' => 'one'])->assertOk();
    $this->postJson('/api/v1/auth/wechat', ['code' => 'two'])->assertOk();

    expect(User::query()->count())->toBe(2);
});

it('rejects an invalid wechat code', function () {
    $this->postJson('/api/v1/auth/wechat', ['code' => 'invalid-code'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

it('requires a token for the current user', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('throttles repeated wechat login attempts', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/v1/auth/wechat', ['code' => 'burst-'.$attempt])->assertOk();
    }

    $this->postJson('/api/v1/auth/wechat', ['code' => 'burst-11'])
        ->assertTooManyRequests()
        ->assertJsonStructure(['message']);
});
