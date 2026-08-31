<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_scan_runs', function (Blueprint $table) {
            $table->json('intent_tags')->nullable()->after('discovery_source');
        });

        Schema::table('business_leads', function (Blueprint $table) {
            $table->json('intent_tags')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('lead_scan_runs', function (Blueprint $table) {
            $table->dropColumn('intent_tags');
        });

        Schema::table('business_leads', function (Blueprint $table) {
            $table->dropColumn('intent_tags');
        });
    }
};
