<?php

// resources/views/livewire/team-members.blade.php

use Livewire\Volt\Component;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TeamInvitation;

new class extends Component {
    public Team $team;
    public string $email = '';
    public string $role = 'member';

    public function mount(Team $team)
    {
        $this->team = $team;
    }

    public function addMember()
    {
        $this->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'string', 'in:member,admin'],
        ]);

        $user = User::where('email', $this->email)->first();

        if (
            $this->team
                ->users()
                ->where('user_id', $user->id)
                ->exists()
        ) {
            $this->addError('email', 'User is already a member of this team.');
            return;
        }

        // Get highest rank in the team
        $highestRank = $this->team->users()->where('status', 'approved')->max('rank') ?? 0;

        // Create pending invitation with next rank
        $this->team->users()->attach($user, [
            'role' => $this->role,
            'status' => 'pending',
            'rank' => $highestRank + 1,
        ]);

        // Send notification to user
        Notification::send($user, new TeamInvitation($this->team));

        $this->email = '';
        $this->role = 'member';

        $this->dispatch('member-invited');
    }

    public function removeMember(User $user)
    {
        if (!$this->isAdmin()) {
            $this->addError('remove', 'Only team admins can remove members.');
            return;
        }

        if ($this->team->user_id === $user->id) {
            $this->addError('remove', 'Team owner cannot be removed.');
            return;
        }

        $this->team->users()->detach($user);

        $this->dispatch('member-removed');
    }

    public function isAdmin(): bool
    {
        $teamUser = $this->team->users()->where('user_id', Auth::id())->where('status', 'approved')->first();

        return $teamUser?->pivot?->role === 'admin';
    }

    public function with(): array
    {
        return [
            'members' => $this->team
                ->users()
                ->orderBy('rank')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->pivot->role,
                        'status' => $user->pivot->status,
                        'rank' => $user->pivot->rank,
                        'isOwner' => $this->team->user_id === $user->id,
                    ];
                }),
        ];
    }
}; ?>

<div>
    <!-- Add Member Form -->
    <div class="p-4 bg-white rounded-lg shadow dark:bg-slate-700">
        <h3 class="text-lg font-medium text-slate-300">Invite Team Member</h3>
        <form wire:submit="addMember" class="mt-4 space-y-4">
            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input wire:model="email" id="email" type="email" class="block w-full mt-1" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="role" value="Role" />
                <select wire:model="role" id="role"
                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-slate-900 dark:text-slate-300">
                    <option value="member">Member</option>
                    <option value="admin">Admin</option>
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-2" />
            </div>

            <x-primary-button>
                Send Invitation
            </x-primary-button>
        </form>
    </div>

    <!-- Team Members List -->
    <div class="mt-6 dark:text-slate-300">
        <h3 class="text-lg font-medium dark:text-slate-300">Team Members</h3>
        <x-input-error :messages="$errors->get('remove')" class="mt-2" />
        <div class="mt-4 space-y-4 ">
            @foreach ($members as $member)
                <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow dark:bg-slate-700">
                    <div>
                        <div class="font-medium">{{ $member['name'] }}</div>
                        <div class="text-sm text-gray-500">{{ $member['email'] }}</div>
                        <div class="text-sm text-gray-500">Role: {{ $member['role'] }}</div>
                        <div
                            class="text-sm {{ $member['status'] === 'pending' ? 'text-yellow-500' : 'text-green-500' }}">
                            Status: {{ ucfirst($member['status']) }}
                        </div>
                        <div class="text-sm text-gray-500">Rank: {{ $member['rank'] }}</div>
                    </div>
                    @if (!$member['isOwner'] && $this->isAdmin())
                        <button wire:click="removeMember({{ $member['id'] }})" class="text-red-600 hover:text-red-800">
                            Remove
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
