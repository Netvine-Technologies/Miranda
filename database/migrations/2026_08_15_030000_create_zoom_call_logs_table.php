<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoom_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_key')->unique();
            $table->string('zoom_call_id')->nullable()->index();
            $table->string('zoom_call_log_id')->nullable()->index();
            $table->string('source', 30)->default('smart_embed');
            $table->string('direction', 20)->nullable();
            $table->string('result', 100)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('caller_number', 80)->nullable();
            $table->string('callee_number', 80)->nullable();
            $table->string('external_number', 80)->nullable()->index();
            $table->timestampTz('occurred_at')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoom_call_logs');
    }
};
