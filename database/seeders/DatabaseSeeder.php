<?php

namespace Database\Seeders;

use App\Models\User;
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
        // Check if users exist before creating
        $playerOne = User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'alias' => 'Zook3r',
            'discord_id' => 619851421222830103,
            'password' => Hash::make('testpassword'), // Ensure password is hashed
            'rank' => 1,
        ]);

        $playerTwo = User::firstOrCreate([
            'email' => 'test2@example.com',
        ], [
            'name' => 'Kagiso',
            'alias' => 'Ramzii',
            'discord_id' => 768838379865374730,
            'password' => Hash::make('testpassword'), // Ensure password is hashed
            'rank' => 2,
        ]);

        $playerThree = User::firstOrCreate([
            'email' => 'test3@example.com',
        ], [
            'name' => 'Molefe',
            'password' => Hash::make('testpassword'), // Ensure password is hashed
            'rank' => 3,
        ]);

        $playerThree = User::firstOrCreate([
            'email' => 'test4@example.com',
        ], [
            'name' => 'LSG',
            'discord_id' => 478844521266806804,
            'password' => Hash::make('testpassword'), // Ensure password is hashed
            'rank' => 4,
        ]);

        // Create a challenge between Player One and Player Two
        $challengeOne = Challenge::create([
            'challenger_id' => $playerOne->id,
            'opponent_id' => $playerTwo->id,
            'status' => 'completed', // Assuming the challenge was completed
            'banned_agent' => 'Sentinel', // Example of a banned agent
            'witness_id' => $playerThree->id, // Player Three acts as a witness
        ]);

        // Record the rank history for Player One after the challenge
        RankHistory::create([
            'user_id' => $playerOne->id,
            'previous_rank' => 1,
            'new_rank' => 2, // Assuming Player One lost and their new rank is 2
            'challenge_id' => $challengeOne->id,
        ]);

        // Record the rank history for Player Two after the challenge
        RankHistory::create([
            'user_id' => $playerTwo->id,
            'previous_rank' => 2,
            'new_rank' => 1, // Assuming Player Two won and their new rank is 1
            'challenge_id' => $challengeOne->id,
        ]);

        // Optionally, you can create more users, challenges, and rank histories
    }
}
