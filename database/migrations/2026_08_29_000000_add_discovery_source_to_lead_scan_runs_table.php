<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_scan_runs', function (Blueprint $table) {
            $table->string('discovery_source')->default('google_places')->after('location');
            $table->index(['discovery_source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('lead_scan_runs', function (Blueprint $table) {
            $table->dropIndex(['discovery_source', 'created_at']);
            $table->dropColumn('discovery_source');
        });
    }
};
