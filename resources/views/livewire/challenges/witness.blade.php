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
    public array $winnerIds = [];

    public function mount(): void
    {
        $this->loadChallenges();
    }

    public function loadChallenges(): void
    {
        try {
            $this->challenges = Challenge::with(['challenger.teams', 'opponent.teams', 'team'])
                ->where('witness_id', Auth::id())
                ->where('status', 'accepted')
                ->latest()
                ->get();

            $this->winnerIds = $this->challenges->pluck('id', 'id')->map(fn() => null)->toArray();
        } catch (\Exception $e) {
            Log::error('Error loading witness challenges', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);
            $this->dispatch('challenge-error', ['message' => 'Failed to load challenges.']);
        }
    }

    public function submitOutcome(int $challengeId): void
    {
        if ($this->loading) return;

        $this->loading = true;

        try {
            $winnerId = $this->winnerIds[$challengeId] ?? null;

            if (empty($winnerId)) {
                $this->dispatch('challenge-error', ['message' => 'Please select a winner before submitting.']);
                return;
            }

            $challenge = Challenge::with(['challenger.teams', 'opponent.teams', 'team'])
                ->where('id', $challengeId)
                ->where('witness_id', Auth::id())
                ->where('status', 'accepted')
                ->firstOrFail();

            if (!in_array($winnerId, [$challenge->challenger_id, $challenge->opponent_id])) {
                $this->dispatch('challenge-error', ['message' => 'Invalid winner selected.']);
                return;
            }

            DB::transaction(function () use ($challenge, $winnerId) {
                // Explicitly convert IDs to integers for comparison
                $challengerId = (int) $challenge->challenger_id;
                $opponentId = (int) $challenge->opponent_id;
                $winnerIdInt = (int) $winnerId;
                
                // Determine loser based on winner selection
                if ($winnerIdInt === $challengerId) {
                    $loserId = $opponentId;
                } elseif ($winnerIdInt === $opponentId) {
                    $loserId = $challengerId;
                } else {
                    throw new \Exception('Invalid winner ID - does not match challenger or opponent');
                }

                $winner = User::findOrFail($winnerIdInt);
                $loser = User::findOrFail($loserId);
                
                // Log for debugging
                Log::info('Challenge outcome processing', [
                    'challenge_id' => $challenge->id,
                    'challenger_id' => $challengerId,
                    'opponent_id' => $opponentId,
                    'winner_id' => $winnerIdInt,
                    'loser_id' => $loserId,
                    'winner_name' => $winner->name,
                    'loser_name' => $loser->name,
                ]);

                // Start with challenge->team_id, but robustly resolve from pivots if null
                $resolvedTeamId = $challenge->team_id;

                // Try to get pivots using challenge->team_id if available
                $winnerPivot = null;
                $loserPivot = null;

                if ($resolvedTeamId) {
                    $winnerPivot = $winner->teams()->where('team_id', $resolvedTeamId)->first()?->pivot;
                    $loserPivot  = $loser->teams()->where('team_id', $resolvedTeamId)->first()?->pivot;
                }

                // If any pivot not found or team_id is null, attempt to find a common team_id
                if (!$winnerPivot || !$loserPivot) {
                    $winnerTeamIds = $winner->teams()->pluck('team_id')->toArray();
                    $loserTeamIds  = $loser->teams()->pluck('team_id')->toArray();

                    $common = array_values(array_intersect($winnerTeamIds, $loserTeamIds));
                    $commonTeamId = $common[0] ?? null;

                    if ($commonTeamId) {
                        $resolvedTeamId = $commonTeamId;
                        $winnerPivot = $winner->teams()->where('team_id', $resolvedTeamId)->first()?->pivot;
                        $loserPivot  = $loser->teams()->where('team_id', $resolvedTeamId)->first()?->pivot;
                    }
                }

                // Last-resort fallback: use the first pivot available for each user (best-effort)
                if ((!$winnerPivot || !$loserPivot) && empty($resolvedTeamId)) {
                    $winnerFirst = $winner->teams()->first();
                    $loserFirst  = $loser->teams()->first();

                    $winnerPivot = $winnerPivot ?? $winnerFirst?->pivot;
                    $loserPivot  = $loserPivot  ?? $loserFirst?->pivot;

                    $resolvedTeamId = $resolvedTeamId ?? $winnerFirst?->pivot->team_id ?? $loserFirst?->pivot->team_id ?? null;
                }

                // If we still don't have pivots or a team id, abort the transaction with a clear error
                if (!$winnerPivot || !$loserPivot || empty($resolvedTeamId)) {
                    Log::error('Unable to resolve team membership for challenge outcome', [
                        'challenge_id' => $challenge->id,
                        'challenge_team_id' => $challenge->team_id,
                        'resolved_team_id' => $resolvedTeamId,
                        'winner_id' => $winner->id,
                        'loser_id' => $loser->id,
                    ]);
                    throw new \Exception('Unable to resolve team or membership pivots for winner/loser.');
                }

                // Capture old ranks BEFORE making changes
                $winnerOldRank = (int) $winnerPivot->rank;
                $loserOldRank  = (int) $loserPivot->rank;

                // Decide if swap is needed (winner had worse/higher number rank)
                $ranksSwapped = $winnerOldRank > $loserOldRank;

                if ($ranksSwapped) {
                    // Swap ranks using pivot model updates
                    $winnerPivot->update(['rank' => $loserOldRank]);
                    $loserPivot->update(['rank' => $winnerOldRank]);
                }

                // Re-fetch fresh pivot ranks to ensure accurate new values
                $winnerNewRank = (int) $winner->teams()->where('team_id', $resolvedTeamId)->first()->pivot->rank;
                $loserNewRank  = (int) $loser->teams()->where('team_id', $resolvedTeamId)->first()->pivot->rank;

                // Record rank history - resolvedTeamId is guaranteed non-null here
                // The error check above ensures we have a valid team_id
                RankHistory::create([
                    'user_id' => $winner->id,
                    'team_id' => $resolvedTeamId,
                    'previous_rank' => $winnerOldRank,
                    'new_rank' => $winnerNewRank,
                    'challenge_id' => $challenge->id,
                ]);

                RankHistory::create([
                    'user_id' => $loser->id,
                    'team_id' => $resolvedTeamId,
                    'previous_rank' => $loserOldRank,
                    'new_rank' => $loserNewRank,
                    'challenge_id' => $challenge->id,
                ]);

                // Update challenge with correct IDs
                $challenge->update([
                    'status' => 'completed',
                    'winner_id' => $winnerIdInt,
                    'loser_id' => $loserId,
                    'completed_at' => now()
                ]);

                // Send Discord notification with old ranks as well
                $this->sendToDiscord($challenge, $winner, $loser, $ranksSwapped, $winnerOldRank, $loserOldRank, $resolvedTeamId);
            });

            $this->dispatch('challenge-completed', ['message' => 'Challenge outcome submitted successfully!']);
            $this->loadChallenges();
        } catch (\Exception $e) {
            Log::error('Error submitting challenge outcome', [
                'error' => $e->getMessage(),
                'challenge_id' => $challengeId,
                'auth_id' => Auth::id()
            ]);
            $this->dispatch('challenge-error', ['message' => 'Failed to submit challenge outcome. Please try again.']);
        } finally {
            $this->loading = false;
        }
    }

    protected function sendToDiscord(Challenge $challenge, User $winner, User $loser, bool $ranksSwapped, int $winnerOldRank, int $loserOldRank, int $teamId): void
    {
        try {
            $discord = app(\App\Services\DiscordService::class);

            // Use the resolved team_id that was validated in submitOutcome
            $winnerNewRank = $winner->teams()->where('team_id', $teamId)->first()?->pivot->rank ?? $winnerOldRank;
            $loserNewRank  = $loser->teams()->where('team_id', $teamId)->first()?->pivot->rank ?? $loserOldRank;

            // Refresh the challenge model to get the updated winner_id and loser_id
            $challenge->refresh();
            
            // Set the relationships explicitly
            $challenge->setRelation('winner', $winner);
            $challenge->setRelation('loser', $loser);
            
            // Add extra properties for the Discord service
            $challenge->ranks_swapped = $ranksSwapped;
            $challenge->winner_old_rank = $winnerOldRank;
            $challenge->winner_new_rank = $winnerNewRank;
            $challenge->loser_old_rank = $loserOldRank;
            $challenge->loser_new_rank = $loserNewRank;

            $discord->sendChallengeNotification($challenge, 'completed');

            if ($ranksSwapped && $challenge->team) {
                $discord->sendRankingsUpdate($challenge->team);
            }
        } catch (\Exception $e) {
            Log::error('Discord notification failed', ['error' => $e->getMessage(), 'challenge_id' => $challenge->id ?? null]);
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
                wire:click="loadChallenges"
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
                                            <span wire:loading wire:target="submitOutcome({{ $challenge->id }})">
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