<?php

namespace App\Providers;

use App\Contracts\WeChatMiniProgramClient;
use App\Jobs\CancelUnpaidOrder;
use App\Jobs\CloseFlashSale;
use App\Jobs\CreateFlashSaleOrder;
use App\Models\User;
use App\WeChat\FakeWeChatMiniProgramClient;
use App\WeChat\HttpWeChatMiniProgramClient;
use Illuminate\Cache\RateLimiting\Limit;
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
        $this->app->bind(WeChatMiniProgramClient::class, function ($app): WeChatMiniProgramClient {
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
        Gate::define('viewApiDocs', fn (?object $user = null): bool => true);

        Gate::define('admin', function (User $user): bool {
            return $user->is_admin;
        });

        Queue::route(CancelUnpaidOrder::class, 'orders');
        Queue::route(CreateFlashSaleOrder::class, 'orders');
        Queue::route(CloseFlashSale::class, 'orders');

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('wechat-login', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('flash-purchase', function (Request $request) {
            return Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}
