<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unsignedTinyInteger('duration_minutes')->nullable();
        });

        Schema::table('volleyball_matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('duration_minutes')->nullable();
            $table->timestamp('timer_started_at')->nullable();
            $table->unsignedInteger('timer_elapsed_seconds')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('volleyball_matches', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'timer_started_at', 'timer_elapsed_seconds']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
