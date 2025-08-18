<?php

use Livewire\Volt\Component;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TeamInvitation;

new class extends Component {
    public Team $team;
    public string $email = '';
    public string $role = 'member';
    public string $discord_team_id = '';

    public function mount(Team $team)
    {
        $this->team = $team;
        $this->discord_team_id = $team->discord_team_id ?? '';
    }

    public function updateDiscordTeamId()
    {
        $this->validate([
            'discord_team_id' => ['required', 'string', 'max:50'],
        ]);

        $this->team->update([
            'discord_team_id' => $this->discord_team_id
        ]);

        $this->dispatch('discord-team-id-updated');
    }

    public function addMember()
    {
        Log::info('Starting addMember process');
        
        if (!$this->team->discord_team_id) {
            $this->addError('email', 'You must set a Discord Team ID before inviting members.');
            Log::warning('Attempted to add member without Discord Team ID');
            return;
        }

        $this->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'string', 'in:member,admin'],
        ]);

        $user = User::where('email', $this->email)->first();
        
        Log::info('Found user for invitation', [
            'user_id' => $user->id,
            'email' => $user->email,
            'has_discord_id' => !empty($user->discord_id),
            'discord_id' => $user->discord_id
        ]);

        if (!$user->discord_id) {
            $this->addError('email', 'This user needs to set their Discord ID first.');
            return;
        }

        if ($this->team->users()->where('user_id', $user->id)->exists()) {
            $this->addError('email', 'User is already a member of this team.');
            return;
        }

        $highestRank = $this->team->users()->where('status', 'approved')->max('rank') ?? 0;

        DB::beginTransaction();
        try {
            // First attach the user
            $this->team->users()->attach($user, [
                'role' => $this->role,
                'status' => 'pending',
                'rank' => $highestRank + 1,
            ]);

            Log::info('User attached to team', [
                'team_id' => $this->team->id,
                'user_id' => $user->id,
                'role' => $this->role
            ]);

            // Then send notification
            try {
                Log::info('Attempting to send team invitation notification', [
                    'team_id' => $this->team->id,
                    'user_id' => $user->id,
                    'discord_id' => $user->discord_id
                ]);

                // Send notification individually to better track failures
                $user->notify(new TeamInvitation($this->team));

                Log::info('Team invitation notification sent successfully');
            } catch (\Exception $e) {
                Log::error('Failed to send team invitation notification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => $user->id,
                    'team_id' => $this->team->id
                ]);
                throw $e;
            }

            DB::commit();
            
            $this->email = '';
            $this->role = 'member';
            
            $this->dispatch('member-invited');
            
            Log::info('Member invitation process completed successfully');
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in addMember process', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->addError('email', 'Failed to send invitation. Please try again.');
        }

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
        $teamUser = $this->team->users()
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->first();

        return $teamUser?->pivot?->role === 'admin';
    }

    public function with(): array
    {
        return [
            'members' => $this->team
                ->users()
                ->orderBy('rank')
                ->get()
                ->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                    'status' => $user->pivot->status,
                    'rank' => $user->pivot->rank,
                    'isOwner' => $this->team->user_id === $user->id,
                ]),
        ];
    }
};
?>
<div class="space-y-6">
    {{-- Discord Team ID Setup --}}
    <div class="p-4 bg-white rounded-lg shadow dark:bg-slate-700">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Discord Team ID</h3>
        <form wire:submit.prevent="updateDiscordTeamId" class="mt-3 flex space-x-2">
            <input type="text" wire:model="discord_team_id"
                   class="flex-1 border-gray-300 rounded-md shadow-sm dark:bg-slate-900 dark:text-slate-300"
                   placeholder="Enter Discord Team ID" />
            <x-primary-button type="submit">Save</x-primary-button>
        </form>
        <x-input-error :messages="$errors->get('discord_team_id')" class="mt-2" />
    </div>

    {{-- Add Member Form --}}
    <div class="p-4 bg-white rounded-lg shadow dark:bg-slate-700">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Invite Team Member</h3>
        @if(!$team->discord_team_id)
            <p class="mt-2 text-sm text-red-500">⚠ Please set a Discord Team ID before inviting members.</p>
        @endif
        <form wire:submit="addMember" class="mt-4 space-y-4">
            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input wire:model="email" id="email" type="email" class="block w-full mt-1" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="role" value="Role" />
                <select wire:model="role" id="role"
                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-slate-900 dark:text-slate-300">
                    <option value="member">Member</option>
                    <option value="admin">Admin</option>
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-2" />
            </div>

            <x-primary-button :disabled="!$team->discord_team_id">
                Send Invitation
            </x-primary-button>
        </form>
    </div>

    {{-- Team Members List --}}
    <div class="p-4 bg-white rounded-lg shadow dark:bg-slate-700">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Team Members</h3>
        <x-input-error :messages="$errors->get('remove')" class="mt-2" />
        <div class="mt-4 space-y-3">
            @foreach ($members as $member)
                <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800 rounded-md shadow-sm">
                    <div>
                        <div class="font-medium">{{ $member['name'] }}</div>
                        <div class="text-sm text-gray-500">{{ $member['email'] }}</div>
                        <div class="text-sm">Role: <span class="font-medium">{{ ucfirst($member['role']) }}</span></div>
                        <div class="text-sm {{ $member['status'] === 'pending' ? 'text-yellow-500' : 'text-green-500' }}">
                            Status: {{ ucfirst($member['status']) }}
                        </div>
                        <div class="text-sm text-gray-500">Rank: {{ $member['rank'] }}</div>
                    </div>
                    @if (!$member['isOwner'] && $this->isAdmin())
                        <button wire:click="removeMember({{ $member['id'] }})"
                                class="text-red-600 hover:text-red-800 text-sm font-semibold">
                            Remove
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
