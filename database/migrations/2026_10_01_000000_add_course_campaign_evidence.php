<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_scan_runs', function (Blueprint $table) {
            $table->string('course_focus')->nullable();
        });

        Schema::table('business_leads', function (Blueprint $table) {
            $table->json('course_evidence')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('business_leads', fn (Blueprint $table) => $table->dropColumn('course_evidence'));
        Schema::table('lead_scan_runs', fn (Blueprint $table) => $table->dropColumn('course_focus'));
    }
};
