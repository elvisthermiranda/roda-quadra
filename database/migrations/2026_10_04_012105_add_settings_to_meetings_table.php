<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unsignedSmallInteger('gender_weight')->default(100);
            $table->unsignedSmallInteger('skill_weight')->default(1);
            $table->string('ranking_tiebreaker')->default('win_rate');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['gender_weight', 'skill_weight', 'ranking_tiebreaker']);
        });
    }
};
