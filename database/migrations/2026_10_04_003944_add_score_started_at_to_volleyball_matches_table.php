<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('volleyball_matches', function (Blueprint $table) {
            $table->timestamp('score_started_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volleyball_matches', function (Blueprint $table) {
            $table->dropColumn('score_started_at');
        });
    }
};
