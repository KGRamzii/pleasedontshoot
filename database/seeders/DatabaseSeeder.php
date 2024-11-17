<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Team;
use App\Models\Challenge;
use App\Models\RankHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Player One (will be team owner)
        $playerOne = User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'alias' => 'Zook3r',
            'discord_id' => 619851421222830103,
            'password' => Hash::make('testpassword'),

        ]);

        // Create the team first, setting user_id to the team owner
        $team = Team::firstOrCreate([
            'name' => 'Valorant Squad',
        ], [
            'personal_team' => false,
            'user_id' => $playerOne->id, // Associate team with the owner
        ]);

        // Add Player One to the team with rank 1
        $team->users()->attach($playerOne->id, ['role' => 'admin', 'status' => 'approved', 'rank' => 1]);

        // Create Player Two
        $playerTwo = User::firstOrCreate([
            'email' => 'test2@example.com',
        ], [
            'name' => 'Kagiso',
            'alias' => 'Ramzii',
            'discord_id' => 768838379865374730,
            'password' => Hash::make('testpassword'),

        ]);

        // Add Player Two to the team with rank 2
        $team->users()->attach($playerTwo->id, ['role' => 'member', 'status' => 'pending', 'rank' => 2]);

        // Create Player Three
        $playerThree = User::firstOrCreate([
            'email' => 'test3@example.com',
        ], [
            'name' => 'Molefe',
            'password' => Hash::make('testpassword'),

        ]);

        // Add Player Three to the team with rank 3
        $team->users()->attach($playerThree->id, ['role' => 'member', 'status' => 'approved', 'rank' => 3]);

        // Create Player Four
        $playerFour = User::firstOrCreate([
            'email' => 'test4@example.com',
        ], [
            'name' => 'LSG',
            'discord_id' => 478844521266806804,
            'password' => Hash::make('testpassword'),

        ]);

        // Add Player Four to the team with rank 4
        $team->users()->attach($playerFour->id, ['role' => 'member', 'status' => 'approved', 'rank' => 4]);

        // Add logic to dynamically set ranks when a new user joins the team
        // Update the rank of each member after adding new players

        $this->updateRanks($team);

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
        // Record the rank history for Player One after the challenge
        RankHistory::create([
            'user_id' => $playerOne->id,
            'previous_rank' => 1,
            'new_rank' => 2,
            'challenge_id' => $challengeOne->id,
            'team_id' => $team->id, // Add team_id here
        ]);

        // Record the rank history for Player Two after the challenge
        RankHistory::create([
            'user_id' => $playerTwo->id,
            'previous_rank' => 2,
            'new_rank' => 1,
            'challenge_id' => $challengeOne->id,
            'team_id' => $team->id, // Add team_id here
        ]);

    }

    /**
     * Update the ranks of all users in the team based on their order in the pivot table.
     */
    private function updateRanks(Team $team)
    {
        // Get all users in the team ordered by their rank (ascending)
        $users = $team->users()->orderBy('team_user.rank')->get();

        // Update the rank for each user
        $rank = 1;
        foreach ($users as $user) {
            $team->users()->updateExistingPivot($user->id, ['rank' => $rank]);
            $rank++;
        }
    }

}
