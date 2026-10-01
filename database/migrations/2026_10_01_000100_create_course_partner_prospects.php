<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_scan_runs', function (Blueprint $table) {
            $table->string('course_country', 2)->nullable()->index();
        });

        Schema::create('course_partner_prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_lead_id')->nullable()->constrained('business_leads')->nullOnDelete();
            $table->foreignId('latest_scan_run_id')->nullable()->constrained('lead_scan_runs')->nullOnDelete();
            $table->string('country_code', 2);
            $table->string('domain');
            $table->string('name');
            $table->text('website');
            $table->string('location')->nullable();
            $table->json('course_focuses')->nullable();
            $table->json('course_evidence')->nullable();
            $table->json('contact_emails')->nullable();
            $table->string('primary_email', 320)->nullable();
            $table->text('email_source_url')->nullable();
            $table->string('phone')->nullable();
            $table->string('review_status', 32)->default('new');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('last_discovered_at')->nullable();
            $table->timestamps();

            $table->unique(['country_code', 'domain']);
            $table->index(['country_code', 'review_status']);
        });

        Schema::create('course_partner_prospect_scan_run', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_partner_prospect_id')->constrained('course_partner_prospects')->cascadeOnDelete();
            $table->foreignId('lead_scan_run_id')->constrained('lead_scan_runs')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_partner_prospect_id', 'lead_scan_run_id'], 'course_partner_prospect_run_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_partner_prospect_scan_run');
        Schema::dropIfExists('course_partner_prospects');
        Schema::table('lead_scan_runs', function (Blueprint $table) {
            $table->dropIndex(['course_country']);
            $table->dropColumn('course_country');
        });
    }
};
