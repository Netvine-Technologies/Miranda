<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('new_website_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('source', 50);
            $table->date('source_date')->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedTinyInteger('priority_score')->default(0)->index();
            $table->json('matched_terms')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('checked_at')->nullable()->index();
            $table->foreignId('business_lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('new_website_candidates');
    }
};
