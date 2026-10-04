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
        Schema::table('participants', function (Blueprint $table) {
            $table->timestamp('left_at')->nullable();
        });

        Schema::table('volleyball_matches', function (Blueprint $table) {
            $table->boolean('incomplete_confirmed')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volleyball_matches', function (Blueprint $table) {
            $table->dropColumn('incomplete_confirmed');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('left_at');
        });
    }
};
