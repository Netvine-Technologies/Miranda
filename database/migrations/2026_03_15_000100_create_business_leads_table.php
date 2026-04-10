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
        Schema::create('business_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('place_id')->unique();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->string('source')->nullable();
            $table->boolean('scraped')->default(false);
            $table->timestamps();

            $table->index(['source', 'scraped']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_leads');
    }
};
