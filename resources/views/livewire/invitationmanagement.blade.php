<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Team;

new class extends Component {
    public $user;
    public $pendingInvitations;

    public function mount()
    {
        $this->user = auth()->user();
        $this->pendingInvitations = $this->user->pendingInvitations;
    }

    public function acceptInvitation($teamId)
    {
        $this->user->teams()->updateExistingPivot($teamId, ['status' => 'accepted']);
        session()->flash('message', 'Invitation accepted.');
    }

    public function rejectInvitation($teamId)
    {
        $this->user->teams()->detach($teamId);
        session()->flash('message', 'Invitation rejected.');
    }

    public function render()
    {
        return view('livewire.pending-invitations', [
            'pendingInvitations' => $this->pendingInvitations,
        ]);
    }
};
?>

<div>
    <h2>Pending Invitations</h2>
    <ul>
        @foreach ($pendingInvitations as $team)
            <li>
                {{ $team->name }}
                <button wire:click="acceptInvitation({{ $team->id }})">Accept</button>
                <button wire:click="rejectInvitation({{ $team->id }})">Reject</button>
            </li>
        @endforeach
    </ul>

    @if (session()->has('message'))
        <div>{{ session('message') }}</div>
    @endif
</div>
