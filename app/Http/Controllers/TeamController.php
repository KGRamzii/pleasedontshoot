<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function index()
    {
        return view('teams.index', [
            'teams' => Auth::user()->allTeams()
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
        ]);

        $team = Auth::user()->ownedTeams()->create([
            'name' => $validated['name'],
            'personal_team' => false,
        ]);

        return redirect()->route('teams.show', $team)
            ->with('success', 'Team created successfully.');
    }

    public function show(Team $team)
    {
        $user = Auth::user();
        if (!$team->hasUser($user)) {
            abort(403);
        }

        return view('teams.show', [
            'team' => $team->load('owner', 'users'),
        ]);
    }

    public function update(Request $request, Team $team)
    {
        if ($team->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('teams')->ignore($team->id)],
        ]);

        $team->update($validated);

        return back()->with('success', 'Team updated successfully.');
    }

    public function destroy(Team $team)
    {
        if ($team->user_id !== Auth::id()) {
            abort(403);
        }

        if ($team->personal_team) {
            abort(403, 'You cannot delete your personal team.');
        }

        $team->delete();

        return redirect()->route('teams.index')
            ->with('success', 'Team deleted successfully.');
    }
}
