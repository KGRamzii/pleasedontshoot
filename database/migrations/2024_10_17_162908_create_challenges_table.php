<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenger_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('opponent_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'declined', 'completed'])->default('pending');
            $table->string('banned_agent')->nullable(); // Specific agent or type
            $table->foreignId('witness_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->nullable()->after('id');
            $table->foreignId('winner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('status');

            $table->foreignId('loser_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('winner_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenges');
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
    }
};
