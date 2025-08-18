<?php
// resources/views/livewire/team-invitations.blade.php

use Livewire\Volt\Component;
use App\Models\Team;
use App\Services\DiscordService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

new class extends Component {
    public Team $team;

    public function acceptInvitation(Team $team)
    {
        $user = Auth::user();
        $highestRank = (int) $team->users()->wherePivot('status', 'approved')->max('rank') ?? 0;

        $team
            ->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('team_id', $team->id)
            ->wherePivot('status', 'pending')
            ->updateExistingPivot($user->id, [
                'status' => 'approved',
                'rank' => $highestRank + 1,
            ]);

        // Send announcement to Discord channel
        if ($user->discord_id) {
            try {
                app(DiscordService::class)->sendToChannel(
                    env('DISCORD_ANNOUNCE_CHANNEL_ID'),
                    "🎉 **<@{$user->discord_id}>** has joined team **{$team->name}**!"
                );
            } catch (\Exception $e) {
                Log::error('Failed to send Discord announcement', [
                    'error' => $e->getMessage(),
                    'user' => $user->id,
                    'team' => $team->id
                ]);
            }
        }

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

<div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Back Button -->
        <div class="mb-6">
            <a href="{{ route('dashboard') }}"
                class="inline-flex items-center text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Dashboard
            </a>
        </div>

        @if ($this->pendingInvitations->isNotEmpty())
            <div class="p-4 mb-4 rounded-md bg-yellow-50 dark:bg-yellow-900">
                <div class="flex">
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-100">
                            Pending Invitations
                        </h3>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                @foreach ($this->pendingInvitations as $team)
                    <div
                        class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            {{ $team->name }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Invited {{ $team->pivot->created_at->diffForHumans() }}
                        </p>
                        <div class="mt-4 space-x-4">
                            <x-primary-button wire:click="acceptInvitation({{ $team->id }})"
                                class="bg-green-600 hover:bg-green-700 focus:ring-green-500">
                                Accept
                            </x-primary-button>
                            <x-secondary-button wire:click="declineInvitation({{ $team->id }})"
                                class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600">
                                Decline
                            </x-secondary-button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div
                class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                    No Pending Invitations
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    You don't have any team invitations at the moment.
                </p>
            </div>
        @endif
    </div>
</div>
