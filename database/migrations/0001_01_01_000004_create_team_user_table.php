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
        Schema::create('team_user', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('team_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Extra pivot fields
            $table->string('status', 20)->default('pending'); // invitation status
            $table->integer('rank')->nullable();              // rank inside team
            $table->string('role', 20)->default('member');    // member, admin, etc.

            $table->timestamps();

            // Ensure a user can only belong once to a team
            $table->unique(['team_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_user');
    }
};
