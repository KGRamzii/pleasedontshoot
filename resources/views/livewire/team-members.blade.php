<?php

// resources/views/livewire/team-members.blade.php

use Livewire\Volt\Component;
use App\Models\Team;
use App\Models\User;

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

        if ($this->team->users->contains($user)) {
            $this->addError('email', 'User is already a member of this team.');
            return;
        }

        $this->team->users()->attach($user, ['role' => $this->role]);

        $this->email = '';
        $this->role = 'member';

        $this->dispatch('member-added');
    }

    public function removeMember(User $user)
    {
        if ($this->team->user_id === $user->id) {
            $this->addError('remove', 'Team owner cannot be removed.');
            return;
        }

        $this->team->users()->detach($user);

        $this->dispatch('member-removed');
    }
}; ?>

<div>
    <!-- Add Member Form -->
    <div class="p-4 bg-white rounded-lg shadow">
        <h3 class="text-lg font-medium">Add Team Member</h3>
        <form wire:submit="addMember" class="mt-4 space-y-4">
            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input wire:model="email" id="email" type="email" class="block w-full mt-1" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="role" value="Role" />
                <select wire:model="role" id="role"
                    class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="member">Member</option>
                    <option value="admin">Admin</option>
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-2" />
            </div>

            <x-primary-button>
                Add Member
            </x-primary-button>
        </form>
    </div>

    <!-- Team Members List -->
    <div class="mt-6">
        <h3 class="text-lg font-medium">Team Members</h3>
        <div class="mt-4 space-y-4">
            @foreach ($team->users as $member)
                <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow">
                    <div>
                        <div class="font-medium">{{ $member->name }}</div>
                        <div class="text-sm text-gray-500">{{ $member->email }}</div>
                        <div class="text-sm text-gray-500">Role: {{ $member->pivot->role }}</div>
                    </div>
                    @if ($team->user_id !== $member->id)
                        <button wire:click="removeMember({{ $member->id }})" class="text-red-600 hover:text-red-800">
                            Remove
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
