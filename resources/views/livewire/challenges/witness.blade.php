<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use App\Models\User;
use App\Models\RankHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public $challenges;
    public $loading = false;
    public $winnerId = null;

    public function mount()
    {
        $this->loadChallenges();
    }

    public function loadChallenges()
    {
        $userId = Auth::id();
        $this->challenges = Challenge::where('witness_id', $userId)
            ->where('status', 'accepted')
            ->with(['challenger', 'opponent'])
            ->get();
    }

    public function submitOutcome($challengeId)
    {
        $this->validate([
            'winnerId' => 'required|exists:users,id',
        ]);

        $challenge = Challenge::find($challengeId);
        if ($challenge && $challenge->status === 'accepted') {
            $winner = User::find($this->winnerId);
            if (!$winner) {
                session()->flash('error', 'Invalid winner selected.');
                return;
            }

            $loser = $winner->id === $challenge->challenger_id ? User::find($challenge->opponent_id) : User::find($challenge->challenger_id);

            if ($loser) {
                $winnerRank = $winner->rank;
                $loserRank = $loser->rank;

                if ($winnerRank > $loserRank) {
                    $this->swapRanks($winner, $loser, $challenge);
                }

                $challenge->update(['status' => 'completed']);
                $this->sendToDiscord($winner, $loser, $challenge);
                $this->dispatch('challenge-completed');
                $this->loadChallenges();
            }
        }
    }

    protected function sendToDiscord($winner, $loser, $challenge)
    {
        try {
            $webhookUrl = env('DISCORD_WEBHOOK');
            if (!$webhookUrl) {
                \Log::warning('Discord webhook URL not configured');
                return;
            }

            $witness = User::find($challenge->witness_id);

            // Get the banned agent information if it exists
            $bannedAgentData = $challenge->banned_agent ? json_decode($challenge->banned_agent, true) : null;

            $message = [
                'embeds' => [
                    [
                        'title' => 'Challenge Completed!',
                        'color' => 5814783,
                        'fields' => [
                            [
                                'name' => 'Winner',
                                'value' => "**<@{$winner->discord_id}>** (Rank: {$winner->rank})",
                                'inline' => true,
                            ],
                            [
                                'name' => 'Loser',
                                'value' => "**<@{$loser->discord_id}>** (Rank: {$loser->rank})",
                                'inline' => true,
                            ],
                            [
                                'name' => 'Witness',
                                'value' => "**<@{$witness->discord_id}>**",
                                'inline' => true,
                            ],
                            [
                                'name' => 'Rank Status',
                                'value' => $winner->rank > $loser->rank ? 'The winner was already ranked higher.' : 'Ranks have been swapped!',
                                'inline' => false,
                            ],
                        ],
                        'timestamp' => now()->toIso8601String(),
                    ],
                ],
            ];

            // Add banned agent information if it exists
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
        } catch (\Exception $e) {
            \Log::error('Failed to send Discord notification: ' . $e->getMessage());
        }
    }

    protected function sendRankListToDiscord()
    {
        try {
            $webhookUrl = env('DISCORD_WEBHOOK');
            if (!$webhookUrl) {
                \Log::warning('Discord webhook URL not configured');
                return;
            }

            $users = User::orderBy('rank')->get();

            $rankList = "**Updated Rank List:**\n\n";
            foreach ($users as $index => $user) {
                $position = $index + 1;
                $crown = $position === 1 ? '👑 ' : '';
                $medal = $position === 2 ? '🥈 ' : ($position === 3 ? '🥉 ' : '');
                $rankList .= "{$crown}{$medal}**Rank {$user->rank}**: <@{$user->discord_id}>\n";
            }

            $message = [
                'embeds' => [
                    [
                        'title' => 'Rankings Updated!',
                        'description' => $rankList,
                        'color' => 5814783,
                        'timestamp' => now()->toIso8601String(),
                        'footer' => [
                            'text' => 'Rankings automatically updated after challenge completion',
                        ],
                    ],
                ],
            ];

            Http::post($webhookUrl, $message);
        } catch (\Exception $e) {
            \Log::error('Failed to send Discord rank list: ' . $e->getMessage());
        }
    }

    private function swapRanks(User $winner, User $loser, Challenge $challenge)
    {
        $winnerPreviousRank = $winner->rank;
        $loserPreviousRank = $loser->rank;

        // Swap the ranks
        $winner->update(['rank' => $loserPreviousRank]);
        $loser->update(['rank' => $winnerPreviousRank]);

        // Log rank history
        RankHistory::create([
            'user_id' => $winner->id,
            'previous_rank' => $winnerPreviousRank,
            'new_rank' => $loserPreviousRank,
            'challenge_id' => $challenge->id,
        ]);
        RankHistory::create([
            'user_id' => $loser->id,
            'previous_rank' => $loserPreviousRank,
            'new_rank' => $winnerPreviousRank,
            'challenge_id' => $challenge->id,
        ]);

        // Send updated rankings to Discord
        $this->sendRankListToDiscord();
    }
}; ?>

<div x-data="{
    showSuccess: false,
    notificationMessage: ''
}" x-init="$wire.on('challenge-completed', () => {
    showSuccess = true;
    notificationMessage = 'Challenge outcome submitted successfully!';
    setTimeout(() => showSuccess = false, 3000);
})" class="container mt-1 bg-white rounded-lg shadow dark:bg-gray-800">

    <div class="p-5">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Witness Challenges Outcomes</h1>

        <div class="mt-6">
            @if ($challenges->isEmpty())
                <div
                    class="flex flex-col items-center justify-center p-6 text-center bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">No challenges for you to judge.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($challenges as $challenge)
                        <div
                            class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-150">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ optional($challenge->challenger)->name }}
                                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                                (Rank: {{ optional($challenge->challenger)->rank }})
                                            </span>
                                        </span>
                                        <span class="text-gray-500 dark:text-gray-400">vs</span>
                                        <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ optional($challenge->opponent)->name }}
                                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                                (Rank: {{ optional($challenge->opponent)->rank }})
                                            </span>
                                        </span>
                                    </div>

                                    @if ($challenge->banned_agent)
                                        @php
                                            $bannedAgentData = json_decode($challenge->banned_agent, true);
                                        @endphp
                                        <div class="mt-2 flex items-center space-x-2">
                                            <span class="text-sm text-gray-500 dark:text-gray-400">Banned Agent:</span>
                                            <div class="flex items-center space-x-1">
                                                <img src="{{ $bannedAgentData['icon'] }}"
                                                    alt="{{ $bannedAgentData['name'] }}" class="w-6 h-6 rounded-full">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $bannedAgentData['name'] }}
                                                </span>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="mt-4 space-y-2">
                                        <label for="winner-{{ $challenge->id }}"
                                            class="block text-gray-700 dark:text-gray-300">
                                            Select Winner:
                                        </label>
                                        <select id="winner-{{ $challenge->id }}" wire:model.live="winnerId"
                                            class="block w-full px-4 py-2 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="">Select winner...</option>
                                            <option value="{{ $challenge->challenger_id }}">
                                                {{ optional($challenge->challenger)->name }}
                                            </option>
                                            <option value="{{ $challenge->opponent_id }}">
                                                {{ optional($challenge->opponent)->name }}
                                            </option>
                                        </select>
                                        <button
                                            class="w-full px-4 py-2 mt-2 font-semibold text-white transition bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                                            wire:click="submitOutcome({{ $challenge->id }})"
                                            wire:loading.attr="disabled">
                                            <span wire:loading.remove>Submit Outcome</span>
                                            <span wire:loading>
                                                <svg class="inline w-4 h-4 mr-2 animate-spin" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                    </path>
                                                </svg>
                                                Processing...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Notifications -->
        <div x-show="showSuccess" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-2"
            class="fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg bg-green-500 text-white" style="z-index: 50;">
            <div class="flex items-center space-x-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span x-text="notificationMessage"></span>
            </div>
        </div>

        @if (session()->has('error'))
            <div class="fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg bg-red-500 text-white"
                style="z-index: 50;">
                <div class="flex items-center space-x-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif
    </div>
</div>
