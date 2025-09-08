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
    public function run(): void
    {
        // Create Player One (owner)
        $playerOne = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'alias' => 'Zook3r',
                'discord_id' => 619851421222830103,
                'password' => Hash::make('testpassword'),
            ]
        );

        // Create the team
        $team = Team::firstOrCreate(
            ['name' => 'Valorant Squad'],
            [
                'personal_team' => 'false',
                'user_id' => $playerOne->id,
            ]
        );

        // Players
        $playerTwo = User::firstOrCreate(
            ['email' => 'test2@example.com'],
            [
                'name' => 'Kagiso',
                'alias' => 'Ramzii',
                'discord_id' => 768838379865374730,
                'password' => Hash::make('testpassword'),
            ]
        );

        $playerThree = User::firstOrCreate(
            ['email' => 'test3@example.com'],
            [
                'name' => 'Molefe',
                'password' => Hash::make('testpassword'),
            ]
        );

        $playerFour = User::firstOrCreate(
            ['email' => 'test4@example.com'],
            [
                'name' => 'LSG',
                'discord_id' => 478844521266806804,
                'password' => Hash::make('testpassword'),
            ]
        );
        $playerFive = User::firstOrCreate(
            ['email' => 'test5@example.com'],
            [
                'name' => 'Fortune',
                'discord_id' => 829561920851542016,
                'password' => Hash::make('testpassword'),
            ]
        );

        // Attach all players to the team (safe, no duplicates)
        $team->users()->syncWithoutDetaching([
            $playerOne->id => ['role' => 'admin', 'status' => 'approved', 'rank' => 1],
            $playerTwo->id => ['role' => 'member', 'status' => 'pending', 'rank' => 2],
            $playerThree->id => ['role' => 'member', 'status' => 'approved', 'rank' => 3],
            $playerFour->id => ['role' => 'member', 'status' => 'approved', 'rank' => 4],
            $playerFive->id => ['role' => 'member', 'status' => 'pending', 'rank' => 5],
        ]);

        // Create a challenge (only if it doesn’t exist)
        $challengeOne = Challenge::firstOrCreate([
            'challenger_id' => $playerOne->id,
            'opponent_id' => $playerTwo->id,
            'team_id' => $team->id,
        ], [
            'status' => 'completed',
            'banned_agent' => 'Sentinel',
            'witness_id' => $playerThree->id,
        ]);

        // Record rank history (safe, won’t duplicate on re-seed)
        RankHistory::firstOrCreate([
            'user_id' => $playerOne->id,
            'challenge_id' => $challengeOne->id,
            'team_id' => $team->id,
        ], [
            'previous_rank' => 1,
            'new_rank' => 2,
        ]);

        RankHistory::firstOrCreate([
            'user_id' => $playerTwo->id,
            'challenge_id' => $challengeOne->id,
            'team_id' => $team->id,
        ], [
            'previous_rank' => 2,
            'new_rank' => 1,
        ]);
    }
}
