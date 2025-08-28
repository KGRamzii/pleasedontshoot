<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;

class TeamService
{
    public function create(User $user, string $name): Team
    {
        return Team::create([
            'user_id' => $user->id,
            'name' => $name,
            'personal_team' => 'true',
            'discord_team_id' => null, // Assuming no Discord ID is provided initially
        ]);
    }

    public function addMember(Team $team, User $user, string $role = 'member'): void
    {
        $team->users()->attach($user, ['role' => $role]);
    }

    public function removeMember(Team $team, User $user): void
    {
        $team->users()->detach($user);
    }
}
