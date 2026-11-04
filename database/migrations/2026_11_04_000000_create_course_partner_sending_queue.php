<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_partner_campaign_controls', function (Blueprint $table) {
            $table->string('campaign_key', 80)->primary();
            $table->boolean('paused')->default(true);
            $table->unsignedSmallInteger('daily_limit')->default(20);
            $table->unsignedSmallInteger('spacing_minutes')->default(5);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();
        });

        Schema::create('course_partner_sending_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_partner_prospect_id')->constrained('course_partner_prospects')->restrictOnDelete();
            $table->string('campaign_key', 80);
            $table->string('recipient_email', 320);
            $table->string('status', 16)->default('queued');
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('outreach_email_id')->nullable()->constrained('course_partner_outreach_emails')->nullOnDelete();
            $table->string('error_code', 80)->nullable();
            $table->timestamps();

            $table->unique(['course_partner_prospect_id', 'campaign_key'], 'course_partner_queue_one_prospect');
            $table->unique(['campaign_key', 'recipient_email'], 'course_partner_queue_one_address');
            $table->index(['campaign_key', 'status', 'available_at'], 'course_partner_queue_dispatch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_partner_sending_queue');
        Schema::dropIfExists('course_partner_campaign_controls');
    }
};
