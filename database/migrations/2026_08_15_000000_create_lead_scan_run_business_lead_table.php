<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_scan_run_business_lead', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_scan_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_lead_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lead_scan_run_id', 'business_lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_scan_run_business_lead');
    }
};
