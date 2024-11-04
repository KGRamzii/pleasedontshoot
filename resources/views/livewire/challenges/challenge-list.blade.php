<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use function Livewire\Volt\{state};

new class extends Component {
    public $challenges;
    public $loading = false;

    public function mount()
    {
        $this->loadChallenges();
    }

    public function loadChallenges()
    {
        $userId = Auth::id();
        $this->challenges = Challenge::where('opponent_id', $userId)
            ->where('status', 'pending')
            ->with(['challenger', 'witness']) // Eager load relationships
            ->get();
    }

    public function acceptChallenge($challengeId)
    {
        $this->loading = true;

        $challenge = Challenge::find($challengeId);
        if ($challenge) {
            $challenge->update(['status' => 'accepted']);

            $this->sendDiscordNotification($challenge, 'accepted');
            $this->dispatch('challenge-updated', [
                'type' => 'success',
                'message' => 'Challenge accepted successfully!',
            ]);
            $this->loadChallenges();
        }

        $this->loading = false;
    }

    public function declineChallenge($challengeId)
    {
        $this->loading = true;

        $challenge = Challenge::find($challengeId);
        if ($challenge) {
            $challenge->update(['status' => 'declined']);

            $this->sendDiscordNotification($challenge, 'declined');
            $this->dispatch('challenge-updated', [
                'type' => 'success',
                'message' => 'Challenge declined successfully!',
            ]);
            $this->loadChallenges();
        }

        $this->loading = false;
    }

    private function sendDiscordNotification($challenge, $action)
    {
        $webhookUrl = env('DISCORD_WEBHOOK');
        $bannedAgentData = $challenge->banned_agent ? json_decode($challenge->banned_agent, true) : null;

        $message = [
            'embeds' => [
                [
                    'title' => 'Challenge ' . ucfirst($action) . '!',
                    'color' => $action === 'accepted' ? 5025616 : 15548997, // Green for accept, Red for decline
                    'fields' => [
                        [
                            'name' => 'Challenger',
                            'value' => "<@{$challenge->challenger->discord_id}>",
                            'inline' => true,
                        ],
                        [
                            'name' => 'Opponent',
                            'value' => "<@{$challenge->opponent->discord_id}>",
                            'inline' => true,
                        ],
                        [
                            'name' => 'Witness',
                            'value' => "<@{$challenge->witness->discord_id}>",
                            'inline' => true,
                        ],
                    ],
                ],
            ],
        ];

        if ($bannedAgentData) {
            $message['embeds'][0]['fields'][] = [
                'name' => 'Banned Agent',
                'value' => "**{$bannedAgentData['name']}**",
                'inline' => true,
            ];
            $message['embeds'][0]['thumbnail'] = [
                'url' => $bannedAgentData['icon'],
            ];
        }

        Http::post($webhookUrl, $message);
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
    setTimeout(() => showNotification = false, 3000);
})" class="container mt-1 bg-white rounded-lg shadow dark:bg-gray-800">

    <div class="p-5">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Pending Challenges</h1>

        <div class="mt-6">
            @if ($challenges->isEmpty())
                <div
                    class="flex flex-col items-center justify-center p-6 text-center bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">No pending challenges.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($challenges as $challenge)
                        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ $challenge->challenger->name }}
                                        </span>
                                        <span class="text-gray-500 dark:text-gray-400">has challenged you!</span>
                                    </div>

                                    <div class="mt-2 space-y-2">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Witness: <span class="font-medium">{{ $challenge->witness->name }}</span>
                                        </p>

                                        @if ($challenge->banned_agent)
                                            @php
                                                $bannedAgent = json_decode($challenge->banned_agent, true);
                                            @endphp
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Banned
                                                    Agent:</span>
                                                <div class="flex items-center space-x-1">
                                                    <img src="{{ $bannedAgent['icon'] }}"
                                                        alt="{{ $bannedAgent['name'] }}" class="w-6 h-6 rounded-full">
                                                    <span class="font-medium text-gray-900 dark:text-white">
                                                        {{ $bannedAgent['name'] }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex space-x-2">
                                    <button
                                        class="px-4 py-2 text-sm font-medium text-white transition bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                        wire:click="acceptChallenge({{ $challenge->id }})" wire:loading.attr="disabled">
                                        Accept
                                    </button>
                                    <button
                                        class="px-4 py-2 text-sm font-medium text-white transition bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        wire:click="declineChallenge({{ $challenge->id }})"
                                        wire:loading.attr="disabled">
                                        Decline
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Notification -->
        <div x-show="showNotification" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-2"
            class="fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg"
            :class="notificationType === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'"
            style="z-index: 50;">
            <p x-text="notificationMessage"></p>
        </div>
    </div>
</div>
