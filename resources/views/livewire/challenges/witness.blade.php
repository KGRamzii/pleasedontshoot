<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use App\Models\User;
use App\Models\RankHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $challenges = [];
    public $loading = false;
    public $winnerIds = [];

    public function mount()
    {
        $this->loadChallenges();
    }

    public function loadChallenges()
    {
        try {
            $userId = Auth::id();
            $this->challenges = Challenge::where('witness_id', $userId)
                ->where('status', 'accepted')
                ->with(['challenger.teams', 'opponent.teams', 'team'])
                ->latest()
                ->get();

            $this->winnerIds = [];
            foreach ($this->challenges as $challenge) {
                $this->winnerIds[$challenge->id] = null;
            }
        } catch (\Exception $e) {
            Log::error('Error loading witness challenges', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
        }
    }

    public function submitOutcome($challengeId)
    {
        if ($this->loading) return;

        $this->loading = true;



        try {
            if (empty($this->winnerIds[$challengeId])) {
                $this->dispatch('challenge-error', [
                    'message' => 'Please select a winner before submitting.',
                ]);
                return;
            }

            $winnerId = $this->winnerIds[$challengeId];

            $challenge = Challenge::with(['challenger.teams', 'opponent.teams', 'team'])
                ->where('id', $challengeId)
                ->where('witness_id', Auth::id())
                ->where('status', 'accepted')
                ->first();

            if (!$challenge) {
                $this->dispatch('challenge-error', [
                    'message' => 'Challenge not found or already processed.',
                ]);
                $this->loadChallenges();
                return;
            }

            if (!in_array($winnerId, [$challenge->challenger_id, $challenge->opponent_id])) {
                $this->dispatch('challenge-error', [
                    'message' => 'Invalid winner selected.',
                ]);
                return;
            }


            $ranksSwapped = false;
            $winnerOldRank = null;
            $winnerNewRank = null;
            $loserOldRank = null;
            $loserNewRank = null;
            $winner = null;
            $loser = null;

            DB::transaction(function () use ($challenge, $winnerId, &$ranksSwapped, &$winnerOldRank, &$winnerNewRank, &$loserOldRank, &$loserNewRank, &$winner, &$loser) {
                $winner = User::findOrFail($winnerId);

                // Correctly identify the loser
                $loserId = ($winnerId == $challenge->challenger_id)
                    ? $challenge->opponent_id
                    : $challenge->challenger_id;

                $loser = User::findOrFail($loserId);

                // Debug logging to see what's happening
                Log::info('Transaction debug', [
                    'challenge_id' => $challenge->id,
                    'winner_id' => $winnerId,
                    'challenger_id' => $challenge->challenger_id,
                    'opponent_id' => $challenge->opponent_id,
                    'calculated_loser_id' => $loserId,
                    'winner_name' => $winner->name,
                    'loser_name' => $loser->name,
                ]);
                DD($challenge);

                $teamId = $challenge->team_id;
                $winnerPivot = $winner->teams()->where('team_id', $teamId)->first()?->pivot;
                $loserPivot = $loser->teams()->where('team_id', $teamId)->first()?->pivot;

                if (!$winnerPivot || !$loserPivot) {
                    throw new \Exception('Winner or loser not in team');
                }

                $winnerOldRank = $winnerPivot->rank;
                $loserOldRank = $loserPivot->rank;

                // Check if winner has a HIGHER rank number (lower position) than loser
                if ($winnerOldRank > $loserOldRank) {
                    // Swap ranks - winner gets the better rank (lower number)
                    $winnerNewRank = $loserOldRank;
                    $loserNewRank = $winnerOldRank;

                    $winner->teams()->updateExistingPivot($teamId, ['rank' => $winnerNewRank]);
                    $loser->teams()->updateExistingPivot($teamId, ['rank' => $loserNewRank]);

                    RankHistory::create([
                        'user_id' => $winner->id,
                        'team_id' => $teamId,
                        'previous_rank' => $winnerOldRank,
                        'new_rank' => $winnerNewRank,
                        'challenge_id' => $challenge->id,
                    ]);

                    RankHistory::create([
                        'user_id' => $loser->id,
                        'team_id' => $teamId,
                        'previous_rank' => $loserOldRank,
                        'new_rank' => $loserNewRank,
                        'challenge_id' => $challenge->id,
                    ]);

                    $ranksSwapped = true;
                } else {
                    // No rank change - winner already has better or equal rank
                    $winnerNewRank = $winnerOldRank;
                    $loserNewRank = $loserOldRank;
                }

                $challenge->update([
                    'status' => 'completed',
                    'winner_id' => $winnerId,
                    'completed_at' => now()
                ]);

            });

            // Reload the challenge with the winner relationship
            $challenge = $challenge->fresh(['winner', 'challenger', 'opponent', 'witness', 'team']);

            // Send Discord notification after transaction with all necessary data
            $this->sendToDiscord($challenge, $winner, $loser, $ranksSwapped, [
                'winner_old_rank' => $winnerOldRank,
                'winner_new_rank' => $winnerNewRank,
                'loser_old_rank' => $loserOldRank,
                'loser_new_rank' => $loserNewRank,
            ]);

            $this->dispatch('challenge-completed', [
                'message' => 'Challenge outcome submitted successfully!'
            ]);

            $this->loadChallenges();

        } catch (\Exception $e) {
            Log::error('Error submitting challenge outcome', [
                'error' => $e->getMessage(),
                'challenge_id' => $challengeId,
                'winner_id' => $this->winnerIds[$challengeId] ?? null,
                'user_id' => Auth::id()
            ]);

            $this->dispatch('challenge-error', [
                'message' => 'Failed to submit challenge outcome. Please try again.',
            ]);
        } finally {
            $this->loading = false;
        }
    }

    protected function sendToDiscord($challenge, $winner, $loser, $ranksSwapped, $rankInfo)
    {
        try {
            $discord = app(\App\Services\DiscordService::class);

            // Reload users with fresh rank data to ensure we have the updated ranks
            $winnerWithRanks = User::with(['teams' => function($query) use ($challenge) {
                $query->where('team_id', $challenge->team_id);
            }])->findOrFail($winner->id);

            $loserWithRanks = User::with(['teams' => function($query) use ($challenge) {
                $query->where('team_id', $challenge->team_id);
            }])->findOrFail($loser->id); // Fixed: Use $loser->id instead of $winner->id

            // Set the user objects and rank information on the challenge for Discord
            $challenge->winner = $winnerWithRanks;
            $challenge->loser = $loserWithRanks; // Fixed: Set to $loserWithRanks
            $challenge->ranks_swapped = $ranksSwapped;

            // Add all rank information to the challenge object
            $challenge->winner_old_rank = $rankInfo['winner_old_rank'];
            $challenge->winner_new_rank = $rankInfo['winner_new_rank'];
            $challenge->loser_old_rank = $rankInfo['loser_old_rank'];
            $challenge->loser_new_rank = $rankInfo['loser_new_rank'];

            // Log the data being sent for debugging
            Log::info('Sending Discord notification', [
                'challenge_id' => $challenge->id,
                'winner_name' => $winnerWithRanks->name,
                'loser_name' => $loserWithRanks->name,
                'ranks_swapped' => $ranksSwapped,
                'winner_old_rank' => $rankInfo['winner_old_rank'],
                'winner_new_rank' => $rankInfo['winner_new_rank'],
                'loser_old_rank' => $rankInfo['loser_old_rank'],
                'loser_new_rank' => $rankInfo['loser_new_rank'],
            ]);

            $discord->sendChallengeNotification($challenge, 'completed');

            // Send rankings update if ranks were swapped
            if ($ranksSwapped && $challenge->team) {
                $discord->sendRankingsUpdate($challenge->team);
            }

        } catch (\Exception $e) {
            Log::error('Discord notification failed', [
                'error' => $e->getMessage(),
                'challenge_id' => $challenge->id,
                'winner_id' => $winner->id ?? null,
                'loser_id' => $loser->id ?? null,
            ]);
        }
    }
};
?>

<div x-data="{
    showNotification: false,
    notificationType: '',
    notificationMessage: ''
}" x-init="
    $wire.on('challenge-completed', ({ message }) => {
        notificationType = 'success';
        notificationMessage = message;
        showNotification = true;
        setTimeout(() => showNotification = false, 5000);
    });
    $wire.on('challenge-error', ({ message }) => {
        notificationType = 'error';
        notificationMessage = message;
        showNotification = true;
        setTimeout(() => showNotification = false, 5000);
    });
" class="container mt-1 bg-white rounded-lg shadow dark:bg-gray-800">

    <div class="p-5">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Witness Challenge Outcomes</h1>
            <button
                wire:click="refresh"
                class="px-3 py-1 text-sm text-gray-600 transition-colors dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                title="Refresh challenges">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </button>
        </div>

        <div class="mt-6">
            @if (empty($challenges) || $challenges->isEmpty())
                <div
                    class="flex flex-col items-center justify-center p-6 text-center rounded-lg bg-gray-50 dark:bg-gray-700">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">No challenges for you to judge.</p>
                    <p class="text-sm text-gray-500 dark:text-gray-500">Accepted challenges will appear here for judging.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($challenges as $challenge)
                        <div class="p-4 transition-colors duration-150 border-l-4 border-blue-500 rounded-lg bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2 space-x-2">
                                        @if($challenge->team)
                                            <span class="px-2 py-1 text-xs text-blue-800 bg-blue-100 rounded-full dark:bg-blue-800 dark:text-blue-200">
                                                {{ $challenge->team->name }}
                                            </span>
                                        @endif
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            Created {{ $challenge->created_at->diffForHumans() }}
                                        </span>
                                    </div>

                                    <div class="flex items-center mb-4 space-x-2">
                                        <div class="flex items-center space-x-1">
                                            <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                                {{ $challenge->challenger->name ?? 'Unknown User' }}
                                            </span>
                                            @if($challenge->challenger && $challenge->team)
                                                @php
                                                    $challengerTeamUser = $challenge->challenger->teams()->where('team_id', $challenge->team_id)->first();
                                                @endphp
                                                @if($challengerTeamUser)
                                                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                                        (Rank: {{ $challengerTeamUser->pivot->rank }})
                                                    </span>
                                                @endif
                                            @endif
                                        </div>

                                        <span class="font-bold text-gray-500 dark:text-gray-400">vs</span>

                                        <div class="flex items-center space-x-1">
                                            <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                                {{ $challenge->opponent->name ?? 'Unknown User' }}
                                            </span>
                                            @if($challenge->opponent && $challenge->team)
                                                @php
                                                    $opponentTeamUser = $challenge->opponent->teams()->where('team_id', $challenge->team_id)->first();
                                                @endphp
                                                @if($opponentTeamUser)
                                                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                                        (Rank: {{ $opponentTeamUser->pivot->rank }})
                                                    </span>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    @if ($challenge->banned_agent)
                                        @php
                                            $bannedAgentData = json_decode($challenge->banned_agent, true);
                                        @endphp
                                        <div class="flex items-center mb-4 space-x-2">
                                            <span class="text-sm text-gray-500 dark:text-gray-400">Banned Agent:</span>
                                            <div class="flex items-center space-x-1">
                                                @if(isset($bannedAgentData['icon']))
                                                    <img src="{{ $bannedAgentData['icon'] }}"
                                                        alt="{{ $bannedAgentData['name'] ?? 'Unknown Agent' }}"
                                                        class="w-6 h-6 rounded-full"
                                                        onerror="this.style.display='none'">
                                                @endif
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $bannedAgentData['name'] ?? 'Unknown Agent' }}
                                                </span>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="space-y-3">
                                        <label for="winner-{{ $challenge->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Select Winner:
                                        </label>
                                        <select
                                            id="winner-{{ $challenge->id }}"
                                            wire:model.live="winnerIds.{{ $challenge->id }}"
                                            class="block w-full px-4 py-2 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                            @disabled($loading)>
                                            <option value="">Select winner...</option>
                                            <option value="{{ $challenge->challenger_id }}">
                                                🥊 {{ $challenge->challenger->name ?? 'Unknown' }} (Challenger)
                                            </option>
                                            <option value="{{ $challenge->opponent_id }}">
                                                🎯 {{ $challenge->opponent->name ?? 'Unknown' }} (Opponent)
                                            </option>
                                        </select>

                                        @if(!empty($winnerIds[$challenge->id]))
                                            @php
                                                $selectedWinner = $winnerIds[$challenge->id] == $challenge->challenger_id ? $challenge->challenger : $challenge->opponent;
                                                $selectedLoser = $winnerIds[$challenge->id] == $challenge->challenger_id ? $challenge->opponent : $challenge->challenger;
                                                $winnerTeamUser = $selectedWinner->teams()->where('team_id', $challenge->team_id)->first();
                                                $loserTeamUser = $selectedLoser->teams()->where('team_id', $challenge->team_id)->first();
                                            @endphp
                                            @if($winnerTeamUser && $loserTeamUser && $winnerTeamUser->pivot->rank > $loserTeamUser->pivot->rank)
                                                <div class="p-2 text-sm border border-yellow-200 rounded bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800">
                                                    <span class="text-yellow-700 dark:text-yellow-300">
                                                        ⚡ Ranks will be swapped! {{ $selectedWinner->name }} will move from rank {{ $winnerTeamUser->pivot->rank }} to {{ $loserTeamUser->pivot->rank }}.
                                                    </span>
                                                </div>
                                            @endif
                                        @endif

                                        <button
                                            class="w-full px-4 py-2 mt-2 font-semibold text-white transition bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed"
                                            wire:click="submitOutcome({{ $challenge->id }})"
                                            @disabled($loading || empty($winnerIds[$challenge->id]))>
                                            <span wire:loading.remove wire:target="submitOutcome({{ $challenge->id }})">
                                                Submit Outcome
                                            </span>
                                            {{-- <span wire:loading wire:target="submitOutcome({{ $challenge->id }})">
                                                <svg class="inline w-4 h-4 mr-2 animate-spin" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                    </path>
                                                </svg>
                                                Processing...
                                            </span> --}}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Loading Overlay - Simple and Clean Method -->
        @if($loading)
            <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50" style="z-index: 100;">
                <div class="p-4 bg-white rounded-lg dark:bg-gray-800">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-blue-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300">Submitting challenge outcome...</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- Notifications -->
        <div x-show="showNotification" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-2"
            class="fixed flex items-center px-4 py-3 space-x-2 rounded-lg shadow-lg bottom-4 right-4"
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
