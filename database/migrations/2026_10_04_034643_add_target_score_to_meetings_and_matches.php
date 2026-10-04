<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unsignedTinyInteger('target_score')->default(15);
        });

        Schema::table('volleyball_matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('target_score')->default(15);
        });
    }

    public function down(): void
    {
        Schema::table('volleyball_matches', function (Blueprint $table) {
            $table->dropColumn('target_score');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('target_score');
        });
    }
};
