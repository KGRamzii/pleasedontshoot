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
        // Check if the authenticated user has permission to add members
        if (!Gate::allows('addTeamMember', $team)) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'string', 'in:member,admin'], // Add any roles you want to support
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Check if user is already a member
        if ($team->users->contains($user)) {
            return back()->with('error', 'User is already a member of this team.');
        }

        // Add the user to the team with the specified role
        $team->users()->attach($user, ['role' => $validated['role']]);

        return back()->with('success', 'Team member added successfully.');
    }

    /**
     * Remove a team member.
     */
    public function destroy(Team $team, User $user)
    {
        // Check if the authenticated user has permission to remove members
        if (!Gate::allows('removeTeamMember', $team)) {
            abort(403);
        }

        // Prevent removing the team owner
        if ($team->user_id === $user->id) {
            return back()->with('error', 'Team owner cannot be removed.');
        }

        // Remove the user from the team
        $team->users()->detach($user);

        return back()->with('success', 'Team member removed successfully.');
    }
}
