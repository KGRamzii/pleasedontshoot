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
        // Authorize the action using the policy method
        if (!Gate::allows('addTeamMember', $team)) {
            abort(403);
        }

        // Validate the input
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'string', 'in:member,admin'],
        ]);

        // Retrieve the user by email
        $user = User::where('email', $validated['email'])->first();

        // Check if user is already a member
        if ($team->users->contains($user)) {
            return back()->with('error', 'User is already a member of this team.');
        }

        // Get the lowest rank and increment by 1
        $lowestRank = $team->users()->min('rank');
        $newRank = $lowestRank ? $lowestRank + 1 : 1; // If no members, start from rank 1

        // Add the user to the team with the specified role and rank
        try {
            $team->users()->attach($user->id, [
                'role' => $validated['role'],
                'rank' => $newRank,
            ]);

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
                return back()->with('error', 'Team owner cannot be removed.');
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
