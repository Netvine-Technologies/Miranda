<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_leads', function (Blueprint $table) {
            $table->timestamp('domain_registered_at')->nullable();
            $table->timestamp('earliest_certificate_at')->nullable();
            $table->timestamp('earliest_archive_at')->nullable();
            $table->timestamp('website_launch_evidence_at')->nullable();
            $table->timestamp('website_estimated_launched_at')->nullable()->index();
            $table->unsignedTinyInteger('website_freshness_score')->nullable()->index();
            $table->string('website_freshness_confidence', 20)->nullable()->index();
            $table->json('website_freshness_evidence')->nullable();
            $table->timestamp('website_freshness_checked_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('business_leads', function (Blueprint $table) {
            $table->dropColumn([
                'domain_registered_at',
                'earliest_certificate_at',
                'earliest_archive_at',
                'website_launch_evidence_at',
                'website_estimated_launched_at',
                'website_freshness_score',
                'website_freshness_confidence',
                'website_freshness_evidence',
                'website_freshness_checked_at',
            ]);
        });
    }
};
