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
        Schema::table('challenges', function (Blueprint $table) {
            // nullable so existing rows don't break
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete()->after('team_id');
            $table->foreignId('loser_id')->nullable()->constrained('users')->nullOnDelete()->after('winner_id');
            $table->timestamp('completed_at')->nullable()->after('loser_id');

            // optional indexes for faster lookups
            $table->index('winner_id');
            $table->index('loser_id');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            // drop indexes first (some DB versions require this explicitly)
            $table->dropIndex(['winner_id']);
            $table->dropIndex(['loser_id']);
            $table->dropIndex(['completed_at']);

            // drop foreign keys & columns
            $table->dropConstrainedForeignId('winner_id');
            $table->dropConstrainedForeignId('loser_id');
            $table->dropColumn('completed_at');
        });
    }
};
