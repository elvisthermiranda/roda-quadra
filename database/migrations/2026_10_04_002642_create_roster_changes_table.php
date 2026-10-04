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
        Schema::create('roster_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('volleyball_matches')->nullOnDelete();
            $table->foreignId('outgoing_participant_id')->nullable()->constrained('participants')->nullOnDelete();
            $table->foreignId('incoming_participant_id')->nullable()->constrained('participants')->nullOnDelete();
            $table->string('type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_changes');
    }
};
