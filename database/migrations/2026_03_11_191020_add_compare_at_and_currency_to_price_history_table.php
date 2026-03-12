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
        Schema::table('price_history', function (Blueprint $table) {
            $table->decimal('compare_at_price', 10, 2)->nullable()->after('price');
            $table->string('currency', 8)->nullable()->after('compare_at_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_history', function (Blueprint $table) {
            $table->dropColumn(['compare_at_price', 'currency']);
        });
    }
};
