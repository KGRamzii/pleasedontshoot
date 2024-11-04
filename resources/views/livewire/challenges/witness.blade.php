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
    public $winnerId = null; // Initialize winnerId

    public function mount()
    {
        $this->loadChallenges();
    }

    public function loadChallenges()
    {
        $userId = Auth::id();
        // Load accepted challenges where the current user is the witness
        $this->challenges = Challenge::where('witness_id', $userId)
            ->where('status', 'accepted')
            ->with(['challenger', 'opponent']) // Eager load relationships
            ->get();
    }

    public function submitOutcome($challengeId)
    {
        $this->validate([
            'winnerId' => 'required|exists:users,id', // Validate winnerId
        ]);

        $challenge = Challenge::find($challengeId);
        if ($challenge && $challenge->status === 'accepted') {
            $winner = User::find($this->winnerId);
            if (!$winner) {
                session()->flash('error', 'Invalid winner selected.');
                return;
            }

            $loser = $winner->id === $challenge->challenger_id ? User::find($challenge->opponent_id) : User::find($challenge->challenger_id);

            // Check if loser is found
            if ($loser) {
                $winnerRank = $winner->rank;
                $loserRank = $loser->rank;

                // Swap ranks only if the winner's rank is lower (numerically higher)
                if ($winnerRank > $loserRank) {
                    $this->swapRanks($winner, $loser, $challenge);
                }

                // Mark challenge as completed
                $challenge->update(['status' => 'completed']);
                $this->sendToDiscord($winner, $loser, $challenge); // Send the outcome to Discord
                $this->dispatch('challenge-completed'); // Dispatch event to notify success
                $this->loadChallenges(); // Reload challenges
            }
        }
    }

    protected function sendToDiscord($winner, $loser, $challenge)
    {
        $webhookUrl = env('DISCORD_WEBHOOK');
        $witness = User::find($challenge->witness_id);

        // Create the embedded message structure
        $message = [
            'embeds' => [
                [
                    'title' => 'Challenge Completed!',
                    'color' => 5814783, // Color for the embed
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
                            'value' => $winner->rank > $loser->rank ? 'The winner was already ranked higher.' : 'The winner was not ranked higher.',
                            'inline' => false,
                        ],
                    ],
                ],
            ],
        ];

        // Send the message to Discord
        Http::post($webhookUrl, $message);
    }

    private function swapRanks(User $winner, User $loser, Challenge $challenge)
    {
        $winnerPreviousRank = $winner->rank;
        $loserPreviousRank = $loser->rank;

        // Swap the ranks
        $winner->update(['rank' => $loserPreviousRank]);
        $loser->update(['rank' => $winnerPreviousRank]);

        // Log rank history for both users
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

        // Send the updated rank list to Discord
        $this->sendRankListToDiscord();
    }
};
?>


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
                    class="flex flex-col items-center justify-center p-6 text-center rounded-lg bg-gray-50 dark:bg-gray-700">
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
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ optional($challenge->challenger)->name }}
                                        </span>
                                        <span class="text-gray-500 dark:text-gray-400">vs</span>
                                        <span class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ optional($challenge->opponent)->name }}
                                        </span>
                                    </div>

                                    <div class="mt-2 space-y-2">
                                        <label for="winner-{{ $challenge->id }}"
                                            class="block mt-2 text-gray-700 dark:text-gray-300">Select Winner:</label>
                                        <select id="winner-{{ $challenge->id }}" wire:model.live="winnerId"
                                            class="block w-full px-4 py-2 border-gray-300 rounded dark:bg-gray-700">
                                            <option value="{{ $challenge->challenger_id }}">
                                                {{ optional($challenge->challenger)->name }}</option>
                                            <option value="{{ $challenge->opponent_id }}">
                                                {{ optional($challenge->opponent)->name }}</option>
                                        </select>
                                        <button
                                            class="px-4 py-2 mt-2 font-semibold text-white transition bg-blue-600 rounded hover:bg-blue-700"
                                            wire:click="submitOutcome({{ $challenge->id }})">Submit Outcome</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div x-show="showSuccess" x-transition.duration.300ms
            class="p-3 mt-3 text-green-800 bg-green-200 rounded alert alert-success">
            <span x-text="notificationMessage"></span>
        </div>

        @if (session()->has('error'))
            <div class="p-3 mt-3 text-red-800 bg-red-200 rounded alert alert-error">
                {{ session('error') }}
            </div>
        @endif
    </div>
</div>
