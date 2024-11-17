<?php

// resources/views/livewire/team-invitations.blade.php

use Livewire\Volt\Component;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public function acceptInvitation(Team $team)
    {
        // Update the status of the pending invitation
        $team
            ->users()
            ->wherePivot('user_id', Auth::id())
            ->wherePivot('team_id', $team->id)
            ->wherePivot('status', 'pending')
            ->updateExistingPivot(Auth::id(), [
                'status' => 'approved',
            ]);

        // Determine the highest rank and cast to integer
        // $highestRank = (int) ($team->users()-
        // >wherePivot('status', 'approved')
        // ->max('pivot_rank') ?? 0);
        $highestRank = $team->users()->withPivot('rank')->max('rank');
        //dd($highestRank);

        // Update the rank for the user in the pivot table
        $team->users()->updateExistingPivot(Auth::id(), [
            'rank' => $highestRank + 1,
        ]);

        // Dispatch an event
        $this->dispatch('invitation-accepted');
    }

    public function declineInvitation(Team $team)
    {
        $team
            ->users()
            ->wherePivot('user_id', Auth::id())
            ->wherePivot('team_id', $team->id)
            ->wherePivot('status', 'pending')
            ->detach(Auth::id());

        $this->dispatch('invitation-declined');
    }

    public function getPendingInvitationsProperty()
    {
        return Auth::user()->teams()->wherePivot('status', 'pending')->get();
    }
}; ?>

<div>
    @if ($this->pendingInvitations->isNotEmpty())
        <div class="rounded-md bg-yellow-50 p-4 mb-4">
            <div class="flex">
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Pending Invitations</h3>
                </div>
            </div>
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($this->pendingInvitations as $team)
            <div class="p-4 bg-white rounded-lg shadow">
                <h3 class="text-lg font-medium">{{ $team->name }}</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Invited {{ $team->pivot->created_at->diffForHumans() }}
                </p>
                <div class="mt-4 space-x-4">
                    <x-primary-button wire:click="acceptInvitation({{ $team->id }})">
                        Accept
                    </x-primary-button>
                    <x-secondary-button wire:click="declineInvitation({{ $team->id }})">
                        Decline
                    </x-secondary-button>
                </div>
            </div>
        @endforeach
    </div>
</div>
