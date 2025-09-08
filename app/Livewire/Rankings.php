<?php

namespace App\Livewire;

use App\Models\Team;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class Rankings extends Component
{
    public $rankings = [];
    public $team;

    public function mount()
    {
        $this->team = Auth::user()->teams()->first();
        if ($this->team) {
            $this->loadRankings();
        }
    }

    public function loadRankings()
    {
        if (!$this->team) return;

        $cacheKey = "team_{$this->team->id}_rankings";
        
        $this->rankings = Cache::remember($cacheKey, 300, function () {
            return $this->team->users()
                ->select('users.id', 'users.name', 'users.alias', 'team_user.rank')
                ->orderByPivot('rank', 'asc')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'alias' => $user->alias,
                        'rank' => $user->pivot->rank,
                    ];
                });
        });
    }

    public function render()
    {
        return view('livewire.rankings');
    }
}
