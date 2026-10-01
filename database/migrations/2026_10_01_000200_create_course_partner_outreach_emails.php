<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_partner_outreach_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_partner_prospect_id')->nullable()->constrained('course_partner_prospects')->nullOnDelete();
            $table->string('campaign_key', 80);
            $table->string('recipient_email', 320);
            $table->string('subject');
            $table->char('tracking_token_hash', 64)->unique();
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('commission_percent');
            $table->unsignedSmallInteger('commission_months');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['course_partner_prospect_id', 'campaign_key'], 'course_partner_one_email_per_campaign');
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_partner_outreach_emails');
    }
};
