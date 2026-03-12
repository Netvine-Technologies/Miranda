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
        Schema::table('price_watch_subscriptions', function (Blueprint $table) {
            $table->timestamp('last_notified_at')->nullable()->after('last_checked_at');
            $table->index(['status', 'last_notified_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_watch_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_notified_at']);
            $table->dropColumn('last_notified_at');
        });
    }
};
