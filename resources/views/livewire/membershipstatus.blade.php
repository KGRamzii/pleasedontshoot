<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Team;

new class extends Component {
    public $user;
    public $teams;

    public function mount()
    {
        $this->user = auth()->user();
        $this->teams = $this->user->teams;
    }
};
?>

<div>
    <h2>Your Team Memberships</h2>
    <ul>
        @foreach ($teams as $team)
            <li>
                {{ $team->name }} -
                @if ($team->pivot && $team->pivot->status)
                    Status: {{ $team->pivot->status }}
                @else
                    Status: Not Available
                @endif
            </li>
        @endforeach
    </ul>

</div>
