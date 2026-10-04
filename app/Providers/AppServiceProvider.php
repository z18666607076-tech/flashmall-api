<?php

namespace App\Providers;

use App\Contracts\WeChatMiniProgramClient;
use App\Demo\DemoMode;
use App\Jobs\CancelUnpaidOrder;
use App\Jobs\CloseFlashSale;
use App\Jobs\CreateFlashSaleOrder;
use App\Models\User;
use App\WeChat\FakeWeChatMiniProgramClient;
use App\WeChat\HttpWeChatMiniProgramClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        DemoMode::apply();

        $this->app->bind(WeChatMiniProgramClient::class, function ($app): WeChatMiniProgramClient {
            DemoMode::apply();

            return match (config('wechat.driver')) {
                'http' => $app->make(HttpWeChatMiniProgramClient::class),
                'fake' => $app->make(FakeWeChatMiniProgramClient::class),
                default => throw new InvalidArgumentException(
                    'Unknown WeChat driver ['.config('wechat.driver').'].',
                ),
            };
        });
    }

    public function boot(): void
    {
        if (config('proxy.trusted')) {
            TrustProxies::at('*');
        }

        Gate::define('viewApiDocs', fn (?object $user = null): bool => true);

        Gate::define('admin', function (User $user): bool {
            return $user->is_admin;
        });

        Queue::route(CancelUnpaidOrder::class, 'orders');
        Queue::route(CreateFlashSaleOrder::class, 'orders');
        Queue::route(CloseFlashSale::class, 'orders');

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('throttle.api_per_minute')))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('wechat-login', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('throttle.login_per_minute')))
                ->by((string) $request->ip());
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('throttle.checkout_per_minute')))
                ->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('flash-purchase', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('throttle.flash_purchase_per_minute')))
                ->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}
