<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('display_token', 40)->nullable()->unique();
        });

        DB::table('meetings')->select('id')->chunkById(100, function (Collection $meetings): void {
            foreach ($meetings as $meeting) {
                DB::table('meetings')->where('id', $meeting->id)->update(['display_token' => Str::random(40)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('display_token');
        });
    }
};
