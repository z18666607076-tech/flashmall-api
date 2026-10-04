<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sku_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->unsignedBigInteger('price_cents');
            $table->char('currency', 3);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('total_stock');
            $table->unsignedInteger('per_user_limit');
            $table->unsignedInteger('sold_count')->default(0);
            $table->string('status')->index();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('flash_sale_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['flash_sale_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_orders');
        Schema::dropIfExists('flash_sales');
    }
};
