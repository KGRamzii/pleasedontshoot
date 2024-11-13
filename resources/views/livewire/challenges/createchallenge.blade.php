<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use App\Models\User;
use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use function Livewire\Volt\{state};
use Illuminate\Support\Facades\Http;

new class extends Component {
    public $challenger_id;
    public $opponent_id = null;
    public $witness_id = null;
    public $banned_agent = '';
    public $status = 'pending';
    public $step = 1;
    public $loading = false;
    public $agentsList = [];
    public $loadingAgents = true;

    #[state]
    public bool $opponentSelected = false;

    #[state]
    public bool $witnessSelected = false;

    public function mount()
    {
        $this->challenger_id = Auth::id();
        $this->loadAgents();
    }

    public function loadAgents()
    {
        $this->loadingAgents = true;

        try {
            $this->agentsList = Cache::remember('valorant.agents', 3600, function () {
                $response = Http::get('https://valorant-api.com/v1/agents');

                if ($response->successful()) {
                    return collect($response->json()['data'])
                        ->where('isPlayableCharacter', true)
                        ->sortBy('displayName')
                        ->values()
                        ->all();
                }

                return [];
            });
        } catch (\Exception $e) {
            $this->agentsList = [];
            // Log the error if necessary
        } finally {
            $this->loadingAgents = false;
        }
    }

    #[Computed]
    public function availableOpponents()
    {
        $challenger = Auth::user();
        return User::where('rank', '>=', $challenger->rank - 1)
            ->where('rank', '<=', $challenger->rank + 1)
            ->where('id', '!=', $challenger->id)
            ->get();
    }

    #[Computed]
    public function availableWitnesses()
    {
        return User::where('id', '!=', $this->challenger_id)
            ->where('id', '!=', $this->opponent_id)
            ->get();
    }

    public function confirmChallenge()
    {
        $this->step = 2;
    }

    public function backToSelection()
    {
        $this->step = 1;
    }

    public function createChallenge()
    {
        $this->validate([
            'opponent_id' => 'required|exists:users,id',
            'witness_id' => 'required|exists:users,id',
            'banned_agent' => 'nullable|string',
        ]);

        $existingChallenge = Challenge::where('challenger_id', $this->challenger_id)
            ->where('opponent_id', $this->opponent_id)
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($existingChallenge) {
            $this->dispatch('challenge-exists', [
                'status' => $existingChallenge->status,
                'message' => 'A challenge has already been sent to ' . optional(User::find($this->opponent_id))->discord_id . ' with status: **' . $existingChallenge->status . '**.',
            ]);
            $this->resetState();
            return;
        }

        $this->loading = true;

        $challenge = Challenge::create([
            'challenger_id' => $this->challenger_id,
            'opponent_id' => $this->opponent_id,
            'witness_id' => $this->witness_id,
            'banned_agent' => $this->banned_agent,
            'status' => $this->status,
        ]);

        $this->sendToDiscord($challenge);

        $this->loading = false;
        $this->dispatch('challenge-created');
        $this->resetState();
    }

    protected function resetState()
    {
        $this->reset('opponent_id', 'witness_id', 'banned_agent');
        $this->step = 1;
    }

    protected function sendToDiscord($challenge)
    {
        $webhookUrl = env('DISCORD_WEBHOOK');

        $bannedAgentData = $challenge->banned_agent ? json_decode($challenge->banned_agent, true) : null;

        $message = [
            'embeds' => [
                [
                    'title' => 'New Challenge Created!',
                    'color' => 5814783,
                    'fields' => [
                        [
                            'name' => 'Challenger',
                            'value' => '**<@' . Auth::user()->discord_id . '>**',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Opponent',
                            'value' => '**<@' . optional(User::find($challenge->opponent_id))->discord_id . '>**',
                            'inline' => true,
                        ],
                        [
                            'name' => 'Witness',
                            'value' => '**<@' . optional(User::find($challenge->witness_id))->discord_id . '>**',
                            'inline' => true,
                        ],
                    ],
                ],
            ],
        ];

        if ($bannedAgentData) {
            $message['embeds'][0]['fields'][] = [
                'name' => 'Banned Agent',
                'value' => '**' . $bannedAgentData['name'] . '**',
                'inline' => true,
            ];

            $message['embeds'][0]['thumbnail'] = [
                'url' => $bannedAgentData['icon'],
            ];
        } else {
            $message['embeds'][0]['fields'][] = [
                'name' => 'Banned Agent',
                'value' => '**None**',
                'inline' => true,
            ];
        }

        Http::post($webhookUrl, $message);
    }

    public function updatedOpponentId()
    {
        $this->opponentSelected = $this->opponent_id !== null;
    }

    public function updatedWitnessId()
    {
        $this->witnessSelected = $this->witness_id !== null;
    }
}; ?>

<div x-data="{
    showSuccess: false,
    showError: false,
    errorMessage: ''
}" x-init="$wire.on('challenge-created', () => {
    showSuccess = true;
    setTimeout(() => showSuccess = false, 3000);
});
$wire.on('challenge-exists', (data) => {
    showError = true;
    errorMessage = data.message;
    setTimeout(() => showError = false, 3000);
});" class="container mt-1 bg-white rounded-lg shadow dark:bg-gray-800">

    <h1 class="text-xl font-bold text-gray-900 dark:text-white p-5">Create New Challenge</h1>
    <div class="p-5 mx-auto">
        <button class="px-4 py-2 font-semibold text-white transition bg-blue-600 rounded hover:bg-blue-700"
            wire:click="$set('step', 1)">
            New Challenge
        </button>

        @if ($step == 1)
            <div class="mt-3">
                <h4 class="text-lg font-bold text-gray-800 dark:text-white">Select an Opponent</h4>
                @if ($this->availableOpponents->isEmpty())
                    <p class="text-red-500">No opponents available to challenge.</p>
                @else
                    <div class="mb-3">
                        <label for="opponent_id" class="block text-gray-700 dark:text-gray-300">Available
                            Opponents</label>
                        <select
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:bg-gray-700 dark:text-white"
                            wire:model.live="opponent_id">
                            <option value="">Choose an opponent</option>
                            @foreach ($this->availableOpponents as $player)
                                <option value="{{ $player->id }}">{{ $player->name }} (Rank: {{ $player->rank }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if ($opponentSelected)
                        <button
                            class="px-4 py-2 font-semibold text-white transition bg-blue-600 rounded hover:bg-blue-700"
                            wire:click.prevent="confirmChallenge">
                            Next: Confirm Challenge
                        </button>
                    @endif
                @endif
            </div>
        @endif

        @if ($step == 2)
            <div class="mt-3">
                <h4 class="text-lg font-bold text-gray-800 dark:text-white">Confirm Challenge</h4>
                <p class="text-gray-600 dark:text-gray-400">Challenger: <strong>{{ Auth::user()->name }}</strong></p>
                <p class="text-gray-600 dark:text-gray-400">Opponent:
                    <strong>{{ optional(User::find($opponent_id))->name }}</strong></p>

                <div class="mb-3">
                    <label for="witness_id" class="block text-gray-700 dark:text-gray-300">Witness</label>
                    <select
                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:bg-gray-700 dark:text-white"
                        wire:model.live="witness_id">
                        <option value="">Select Witness</option>
                        @foreach ($this->availableWitnesses as $player)
                            <option value="{{ $player->id }}">{{ $player->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Agent Selection Grid with improved selection highlighting -->
                <div class="mb-3">
                    <label class="block mb-2 text-gray-700 dark:text-gray-300">Banned Agent (Optional)</label>
                    @if ($loadingAgents)
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                            @foreach (range(1, 12) as $placeholder)
                                <div class="animate-pulse">
                                    <div class="flex flex-col items-center p-2">
                                        <div class="w-16 h-16 bg-gray-200 rounded-full dark:bg-gray-700"></div>
                                        <div class="w-20 h-4 mt-2 bg-gray-200 rounded dark:bg-gray-700"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                            <!-- None option -->
                            <div class="relative cursor-pointer" wire:click="$set('banned_agent', '')">
                                <div
                                    class="flex flex-col items-center p-2 transition-all border-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700
                                    {{ empty($banned_agent) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-transparent' }}">
                                    <div
                                        class="flex items-center justify-center w-16 h-16 bg-gray-200 rounded-full dark:bg-gray-700">
                                        <span class="text-gray-500 dark:text-gray-400">None</span>
                                    </div>
                                    <span class="mt-1 text-sm text-center">None</span>
                                </div>
                            </div>

                            @foreach ($agentsList as $agent)
                                @php
                                    $agentData = json_encode([
                                        'name' => $agent['displayName'],
                                        'icon' => $agent['displayIconSmall'],
                                    ]);
                                    $currentSelectedAgent = json_decode($banned_agent, true);
                                    $isSelected =
                                        !empty($currentSelectedAgent) &&
                                        $currentSelectedAgent['name'] === $agent['displayName'];
                                @endphp

                                <div class="relative cursor-pointer transition-transform hover:-translate-y-1"
                                    wire:click="$set('banned_agent', '{{ $agentData }}')">
                                    <div
                                        class="flex flex-col items-center p-2 transition-all border-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700
                                        {{ $isSelected ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-transparent' }}">
                                        <img src="{{ $agent['displayIconSmall'] }}" alt="{{ $agent['displayName'] }}"
                                            class="w-16 h-16 rounded-full object-cover {{ $isSelected ? 'ring-2 ring-blue-500 ring-offset-2' : '' }}">
                                        <span class="mt-1 text-sm text-center text-gray-700 dark:text-gray-300">
                                            {{ $agent['displayName'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if (!empty($banned_agent))
                            @php
                                $selectedAgent = json_decode($banned_agent, true);
                            @endphp
                            <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <img src="{{ $selectedAgent['icon'] }}" alt="{{ $selectedAgent['name'] }}"
                                        class="w-12 h-12 rounded-full">
                                    <div>
                                        <p class="font-medium dark:text-white">Selected Agent:</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">
                                            {{ $selectedAgent['name'] }}</p>
                                    </div>
                                    <button
                                        class="ml-auto p-2 text-gray-500 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400"
                                        wire:click="$set('banned_agent', '')">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="flex justify-between mt-4">
                    <button
                        class="px-4 py-2 font-semibold text-gray-700 transition bg-gray-300 rounded hover:bg-gray-400 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500"
                        wire:click="backToSelection">
                        Back
                    </button>

                    @if ($witnessSelected)
                        <button
                            class="px-4 py-2 font-semibold text-white transition bg-green-600 rounded hover:bg-green-700"
                            wire:click="createChallenge" wire:loading.attr="disabled">
                            <span wire:loading.remove>Confirm & Create Challenge</span>
                            <span wire:loading class="flex items-center space-x-2">
                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                <span>Creating...</span>
                            </span>
                        </button>
                    @endif
                </div>
            </div>
        @endif

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
                <span>Challenge created successfully!</span>
            </div>
        </div>

        <div x-show="showError" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-2"
            class="fixed bottom-4 right-4 px-4 py-3 rounded-lg shadow-lg bg-rose-600 text-white" style="z-index: 50;">
            <div class="flex items-center space-x-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span x-text="errorMessage || 'A Challenge has already been made...'"></span>
            </div>
        </div>
    </div>
</div>
