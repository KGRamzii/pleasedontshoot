<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Team;
use App\Models\Challenge;
use App\Models\RankHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create the team first
        $team = Team::firstOrCreate([
            'name' => 'Valorant Squad',
        ], [
            'personal_team' => false,
        ]);

        // Create Player One (will be team owner)
        $playerOne = User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'alias' => 'Zook3r',
            'discord_id' => 619851421222830103,
            'password' => Hash::make('testpassword'),
            'rank' => 1,
            'current_team_id' => $team->id,
        ]);

        // Set the team owner
        $team->user_id = $playerOne->id;
        $team->save();

        // Create Player Two
        $playerTwo = User::firstOrCreate([
            'email' => 'test2@example.com',
        ], [
            'name' => 'Kagiso',
            'alias' => 'Ramzii',
            'discord_id' => 768838379865374730,
            'password' => Hash::make('testpassword'),
            'rank' => 2,
            'current_team_id' => $team->id,
        ]);

        // Create Player Three
        $playerThree = User::firstOrCreate([
            'email' => 'test3@example.com',
        ], [
            'name' => 'Molefe',
            'password' => Hash::make('testpassword'),
            'rank' => 3,
            'current_team_id' => $team->id,
        ]);

        // Create Player Four
        $playerFour = User::firstOrCreate([
            'email' => 'test4@example.com',
        ], [
            'name' => 'LSG',
            'discord_id' => 478844521266806804,
            'password' => Hash::make('testpassword'),
            'rank' => 4,
            'current_team_id' => $team->id,
        ]);

        // Add all players to the team
        $team->users()->attach([
            $playerOne->id => ['role' => 'admin'],
            $playerTwo->id => ['role' => 'member'],
            $playerThree->id => ['role' => 'member'],
            $playerFour->id => ['role' => 'member'],
        ]);

        // Create a challenge between Player One and Player Two
        $challengeOne = Challenge::create([
            'challenger_id' => $playerOne->id,
            'opponent_id' => $playerTwo->id,
            'status' => 'completed',
            'banned_agent' => 'Sentinel',
            'witness_id' => $playerThree->id,
            'team_id' => $team->id, // Associate challenge with the team
        ]);

        // Record the rank history for Player One after the challenge
        RankHistory::create([
            'user_id' => $playerOne->id,
            'previous_rank' => 1,
            'new_rank' => 2,
            'challenge_id' => $challengeOne->id,
        ]);

        // Record the rank history for Player Two after the challenge
        RankHistory::create([
            'user_id' => $playerTwo->id,
            'previous_rank' => 2,
            'new_rank' => 1,
            'challenge_id' => $challengeOne->id,
        ]);
    }
}
