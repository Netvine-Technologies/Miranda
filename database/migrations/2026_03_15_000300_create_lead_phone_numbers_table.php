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
        Schema::create('lead_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_lead_id')->constrained('business_leads')->cascadeOnDelete();
            $table->string('phone_number');
            $table->string('source_page')->nullable();
            $table->timestamps();

            $table->unique(['business_lead_id', 'phone_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_phone_numbers');
    }
};
