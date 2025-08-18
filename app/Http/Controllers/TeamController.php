<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use App\services\DiscordService;

class TeamController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (!$user) {
            // Handle unauthenticated user scenario
            return redirect()->route('login')->with('error', 'You need to be logged in to view this page.');
        }

        // Fetch the teams for the logged-in user (or all teams if applicable)
        $teams = Auth::user()->teams; // Or however you are fetching the teams
        //dd($teams); // Debug the teams to check if it's fetched correctly
        return view('teams.index', compact('teams')); // Passing teams to the view
    }

    public function create()
    {
        return view('teams.create');
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
    ]);

    $team = Team::create([
        'name' => $validated['name'],
        'user_id' => Auth::id(),
        'personal_team' => false,
    ]);

    // Notify Discord (mocked)
    app(DiscordService::class)
        ->sendToChannel('TEAM_CHANNEL_ID', "A new team '{$team->name}' has been created!");

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
