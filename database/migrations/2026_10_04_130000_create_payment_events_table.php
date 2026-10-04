<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('provider_reference')->nullable()->after('payment_channel');
            $table->json('payment_payload')->nullable()->after('provider_reference');
            $table->index('provider_reference');
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32);
            $table->string('event_id', 128);
            $table->string('type', 64);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('processed_at')->useCurrent();
            $table->unique(['channel', 'event_id']);
            $table->index(['order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['provider_reference']);
            $table->dropColumn(['provider_reference', 'payment_payload']);
        });
    }
};
