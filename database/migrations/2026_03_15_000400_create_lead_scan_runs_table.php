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
        Schema::create('lead_scan_runs', function (Blueprint $table) {
            $table->id();
            $table->string('query');
            $table->string('location');
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_places_found')->default(0);
            $table->unsignedInteger('details_processed')->default(0);
            $table->unsignedInteger('websites_queued')->default(0);
            $table->unsignedInteger('websites_crawled')->default(0);
            $table->unsignedInteger('emails_found')->default(0);
            $table->unsignedInteger('phone_numbers_found')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_scan_runs');
    }
};
