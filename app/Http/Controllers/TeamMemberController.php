<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeamMemberController extends Controller
{
    /**
     * Add a new team member.
     */
    public function store(Request $request, Team $team)
{
    if (!Gate::allows('addTeamMember', $team)) {
        abort(403);
    }

    $validated = $request->validate([
        'email' => ['required', 'email', 'exists:users,email'],
        'role' => ['required', 'string', 'in:member,admin'],
    ]);

    $user = User::where('email', $validated['email'])->first();

    if ($team->users->contains($user)) {
        return back()->with('error', 'User is already a member of this team.');
    }

    $lowestRank = $team->users()->min('rank');
    $newRank = $lowestRank ? $lowestRank + 1 : 1;

    try {
        $team->users()->attach($user->id, [
            'role' => $validated['role'],
            'rank' => $newRank,
        ]);

        // Notify Discord (mocked)
        $discordService = app(\App\Services\DiscordService::class);
        $discordService->sendDirectMessage(
            $user->discord_id ?? 'UNKNOWN_ID', // assumes you have discord_id column
            "You’ve been invited to join the team '{$team->name}'."
        );

        return back()->with('success', 'Team member added successfully with rank.');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to add user to the team. Please try again.');
    }
}



    /**
     * Remove a team member.
     */
    public function destroy(Team $team, User $user)
    {
        // Use the policy to determine authorization
        if (Gate::allows('removeMember', [$team, $user])) {
            // Prevent removing the team owner
            if ($team->user_id === $user->id) {
                return back()->with('error', 'Team owner cannot be removed!.');
            }

            // Use Eloquent's detach method to remove the user from the team
            try {
                // Detach the user from the team using Eloquent
                $team->users()->detach($user->id);

                return back()->with('success', 'Team member removed successfully.');
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to remove user from the team. Please try again.');
            }
        } else {
            abort(403);
        }


    }
}
