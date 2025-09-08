<?php

namespace App\Livewire;

use App\Models\Team;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class Rankings extends Component
{
    public $teams;
    public $selectedTeamId;
    public $teamRankings;

    public function mount()
    {
        $this->teams = Auth::user()->teams;
        if ($this->teams->isEmpty()) {
            $this->teamRankings = collect();
            $this->selectedTeamId = null;
            return;
        }
        $this->selectedTeamId = $this->teams->first()->id;
        $this->loadTeamRankings();
    }

    public function loadTeamRankings()
    {
        if ($this->selectedTeamId) {
            $this->teamRankings = Auth::user()->teams()->where('teams.id', $this->selectedTeamId)
                ->first()
                ->users()
                ->select('users.*', 'team_user.rank')
                ->orderBy('team_user.rank', 'asc')
                ->get();
        } else {
            $this->teamRankings = collect();
        }
    }

    public function switchTeam($teamId)
    {
        $this->selectedTeamId = $teamId;
        $this->loadTeamRankings();
    }

    public function render()
    {
        return view('livewire.rankings', [
            'teams' => $this->teams,
            'selectedTeamId' => $this->selectedTeamId,
            'teamRankings' => $this->teamRankings,
        ]);
    }
}
