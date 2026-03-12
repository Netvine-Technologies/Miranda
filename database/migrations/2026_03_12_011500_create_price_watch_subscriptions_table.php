<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_watch_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->foreignId('canonical_product_id')->constrained('canonical_products')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('confirm_token')->unique();
            $table->string('unsubscribe_token')->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('last_notified_price', 10, 2)->nullable();
            $table->string('last_notified_currency', 8)->nullable();
            $table->string('last_notified_stock_status')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['email', 'canonical_product_id']);
            $table->index(['status', 'canonical_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_watch_subscriptions');
    }
};
