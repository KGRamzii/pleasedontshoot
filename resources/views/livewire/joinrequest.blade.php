<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Team;

new class extends Component {
    public $teamId;

    public function requestToJoin()
    {
        $user = auth()->user();
        $team = Team::find($this->teamId);

        if ($team) {
            $team->inviteUser($user);
            session()->flash('message', 'Join request submitted successfully.');
        }
    }
}; ?>

<div>
    <h2>Request to Join Team</h2>
    <button wire:click="requestToJoin">Request to Join</button>

    @if (session()->has('message'))
        <div>{{ session('message') }}</div>
    @endif
</div>
