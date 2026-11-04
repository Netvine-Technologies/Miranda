<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_partner_outreach_emails', function (Blueprint $table) {
            $table->char('unsubscribe_token_hash', 64)->nullable()->unique();
        });

        Schema::create('course_partner_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email', 320)->unique();
            $table->string('reason', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_partner_suppressions');
        Schema::table('course_partner_outreach_emails', function (Blueprint $table) {
            $table->dropColumn('unsubscribe_token_hash');
        });
    }
};
