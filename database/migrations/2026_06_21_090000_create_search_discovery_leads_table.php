<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_discovery_leads', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('search_discovery');
            $table->string('city');
            $table->string('niche');
            $table->string('phrase')->nullable();
            $table->text('source_query')->nullable();
            $table->string('result_title');
            $table->text('result_url');
            $table->text('result_snippet')->nullable();
            $table->unsignedInteger('result_position')->nullable();
            $table->string('instagram_handle')->nullable();
            $table->string('instagram_profile_url')->nullable();
            $table->json('matched_terms')->nullable();
            $table->integer('lead_score')->default(0);
            $table->string('lead_classification')->default('needs_manual_review');
            $table->string('status')->default('new');
            $table->json('raw_result_json')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'city', 'niche']);
            $table->index(['status', 'lead_classification']);
            $table->index('instagram_handle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_discovery_leads');
    }
};
