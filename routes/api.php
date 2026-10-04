<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\FlashSaleController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SkuController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class);

    Route::post('auth/wechat', [AuthController::class, 'login']);

    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show'])->whereNumber('id');

    Route::get('flash-sales', [FlashSaleController::class, 'index']);
    Route::get('flash-sales/{flashSale}', [FlashSaleController::class, 'show']);

    Route::post('payments/wechat/notify', [PaymentWebhookController::class, 'wechat']);
    Route::post('payments/stripe/webhook', [PaymentWebhookController::class, 'stripe']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('cart', [CartController::class, 'show']);
        Route::post('cart/items', [CartController::class, 'store']);
        Route::patch('cart/items/{item}', [CartController::class, 'update']);
        Route::delete('cart/items/{item}', [CartController::class, 'destroy']);

        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('orders/{order}/pay', [OrderController::class, 'pay']);
        Route::post('orders/{order}/ship', [OrderController::class, 'ship']);
        Route::post('orders/{order}/complete', [OrderController::class, 'complete']);
        Route::post('orders/{order}/refund', [OrderController::class, 'refund']);

        Route::post('flash-sales', [FlashSaleController::class, 'store']);
        Route::post('flash-sales/{flashSale}/cancel', [FlashSaleController::class, 'cancel']);
        Route::post('flash-sales/{flashSale}/purchase', [FlashSaleController::class, 'purchase']);

        Route::post('categories', [CategoryController::class, 'store']);
        Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        Route::post('products', [ProductController::class, 'store']);
        Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);

        Route::post('products/{product}/skus', [SkuController::class, 'store']);
        Route::match(['put', 'patch'], 'skus/{sku}', [SkuController::class, 'update']);
        Route::delete('skus/{sku}', [SkuController::class, 'destroy']);
    });
});
