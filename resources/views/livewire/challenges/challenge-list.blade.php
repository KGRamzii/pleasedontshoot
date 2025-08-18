<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use function Livewire\Volt\{state};

new class extends Component {
    public $challenges = [];
    public $loading = false;

    public function mount()
    {
        $this->loadChallenges();
    }

    public function loadChallenges()
    {
        try {
            $userId = Auth::id();
            $this->challenges = Challenge::where('opponent_id', $userId)
                ->where('status', 'pending')
                ->with(['challenger', 'witness', 'team']) // Added team relationship
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Error loading challenges', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            $this->dispatch('challenge-updated', [
                'type' => 'error',
                'message' => 'Failed to load challenges. Please refresh the page.',
            ]);
        }
    }

    public function acceptChallenge($challengeId)
    {
        if ($this->loading) return;
        
        $this->loading = true;

        try {
            $challenge = Challenge::with(['challenger', 'opponent', 'witness', 'team'])
                ->where('id', $challengeId)
                ->where('opponent_id', Auth::id())
                ->where('status', 'pending')
                ->first();

            if (!$challenge) {
                $this->dispatch('challenge-updated', [
                    'type' => 'error',
                    'message' => 'Challenge not found or already processed.',
                ]);
                $this->loadChallenges();
                return;
            }

            $challenge->update(['status' => 'accepted']);

            // Send Discord notification
            $this->sendDiscordNotification($challenge, 'accepted');

            $this->dispatch('challenge-updated', [
                'type' => 'success',
                'message' => 'Challenge accepted successfully!',
            ]);
            
            $this->loadChallenges();

        } catch (\Exception $e) {
            Log::error('Error accepting challenge', [
                'error' => $e->getMessage(),
                'challenge_id' => $challengeId,
                'user_id' => Auth::id()
            ]);

            $this->dispatch('challenge-updated', [
                'type' => 'error',
                'message' => 'Failed to accept challenge. Please try again.',
            ]);
        } finally {
            $this->loading = false;
        }
    }

    public function declineChallenge($challengeId)
    {
        if ($this->loading) return;
        
        $this->loading = true;

        try {
            $challenge = Challenge::with(['challenger', 'opponent', 'witness', 'team'])
                ->where('id', $challengeId)
                ->where('opponent_id', Auth::id())
                ->where('status', 'pending')
                ->first();

            if (!$challenge) {
                $this->dispatch('challenge-updated', [
                    'type' => 'error',
                    'message' => 'Challenge not found or already processed.',
                ]);
                $this->loadChallenges();
                return;
            }

            $challenge->update(['status' => 'declined']);

            // Send Discord notification
            $this->sendDiscordNotification($challenge, 'declined');

            $this->dispatch('challenge-updated', [
                'type' => 'success',
                'message' => 'Challenge declined successfully!',
            ]);
            
            $this->loadChallenges();

        } catch (\Exception $e) {
            Log::error('Error declining challenge', [
                'error' => $e->getMessage(),
                'challenge_id' => $challengeId,
                'user_id' => Auth::id()
            ]);

            $this->dispatch('challenge-updated', [
                'type' => 'error',
                'message' => 'Failed to decline challenge. Please try again.',
            ]);
        } finally {
            $this->loading = false;
        }
    }

    private function sendDiscordNotification($challenge, $action)
    {
        try {
            app(\App\Services\DiscordService::class)->sendChallengeNotification($challenge, $action);
        } catch (\Exception $e) {
            Log::error('Failed to send Discord notification', [
                'error' => $e->getMessage(),
                'challenge_id' => $challenge->id,
                'action' => $action
            ]);
            // Don't fail the main operation if Discord notification fails
        }
    }

    public function refresh()
    {
        $this->loadChallenges();
    }
};
?>

<div x-data="{
    showNotification: false,
    notificationType: '',
    notificationMessage: ''
}" x-init="$wire.on('challenge-updated', ({ type, message }) => {
    notificationType = type;
    notificationMessage = message;
    showNotification = true;
    setTimeout(() => showNotification = false, 5000);
})" class="container mt-1 bg-white rounded-lg shadow dark:bg-gray-800">

    <div class="p-5">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Pending Challenges</h1>
            <button 
                wire:click="refresh" 
                class="px-3 py-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors"
                title="Refresh challenges">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </button>
        </div>

        <div class="mt-6">
            @if (empty($challenges) || $challenges->isEmpty())
                <div
                    class="flex flex-col items-center justify-center p-6 text-center bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">No pending challenges.</p>
                    <p class="text-sm text-gray-500 dark:text-gray-500">New challenges will appear here.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($challenges as $challenge)
                        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border-l-4 border-blue-500">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ $challenge->challenger->name ?? 'Unknown User' }}
                                        </span>
                                        <span class="text-gray-500 dark:text-gray-400">has challenged you!</span>
                                        @if($challenge->team)
                                            <span class="px-2 py-1 text-xs bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full">
                                                {{ $challenge->team->name }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-2 space-y-2">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Witness: <span class="font-medium">{{ $challenge->witness->name ?? 'Unknown' }}</span>
                                        </p>

                                        <p class="text-xs text-gray-500 dark:text-gray-500">
                                            Created {{ $challenge->created_at->diffForHumans() }}
                                        </p>

                                        @if ($challenge->banned_agent)
                                            @php
                                                $bannedAgent = json_decode($challenge->banned_agent, true);
                                            @endphp
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Banned Agent:</span>
                                                <div class="flex items-center space-x-1">
                                                    @if(isset($bannedAgent['icon']))
                                                        <img src="{{ $bannedAgent['icon'] }}"
                                                            alt="{{ $bannedAgent['name'] ?? 'Unknown Agent' }}" 
                                                            class="w-6 h-6 rounded-full"
                                                            onerror="this.style.display='none'">
                                                    @endif
                                                    <span class="font-medium text-gray-900 dark:text-white">
                                                        {{ $bannedAgent['name'] ?? 'Unknown Agent' }}
                                                    </span>
                                                </div>
                                            </div>
                                        @else
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                Banned Agent: <span class="text-gray-500">None</span>
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex space-x-2">
                                    <button
                                        class="px-4 py-2 text-sm font-medium text-white transition bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        wire:click="acceptChallenge({{ $challenge->id }})" 
                                        wire:loading.attr="disabled"
                                        wire:target="acceptChallenge"
                                        @disabled($loading)>
                                        <span wire:loading.remove wire:target="acceptChallenge({{ $challenge->id }})">Accept</span>
                                        <span wire:loading wire:target="acceptChallenge({{ $challenge->id }})">Processing...</span>
                                    </button>
                                    <button
                                        class="px-4 py-2 text-sm font-medium text-white transition bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        wire:click="declineChallenge({{ $challenge->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="declineChallenge"
                                        @disabled($loading)>
                                        <span wire:loading.remove wire:target="declineChallenge({{ $challenge->id }})">Decline</span>
                                        <span wire:loading wire:target="declineChallenge({{ $challenge->id }})">Processing...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Loading Overlay - Only show when there are challenges and we're processing them -->
        @if (!empty($challenges) && $challenges->isNotEmpty())
            <div wire:loading.flex wire:target="acceptChallenge,declineChallenge" 
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center" 
                 style="z-index: 100;">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg">
                    <div class="flex items-center space-x-2">
                        <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300">Processing challenge...</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- Notification -->
        <div x-show="showNotification" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-2"
            class="fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg flex items-center space-x-2"
            :class="{
                'bg-green-500 text-white': notificationType === 'success',
                'bg-red-500 text-white': notificationType === 'error',
                'bg-blue-500 text-white': notificationType === 'info'
            }"
            style="z-index: 50;">
            <svg x-show="notificationType === 'success'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <svg x-show="notificationType === 'error'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <p x-text="notificationMessage"></p>
        </div>
    </div>
</div>