<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use App\Models\User;
use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use function Livewire\Volt\{state};

new class extends Component {
    public $challenger_id;
    public $opponent_id = null;
    public $witness_id = null;
    public $banned_agent = ''; // Store agent UUID or name
    public $status = 'pending';
    public $step = 1;
    public $loading = false;
    public $agentsList = [];
    public $loadingAgents = true;

    #[state]
    public ?int $selected_team_id = null;

    #[state]
    public bool $opponentSelected = false;

    #[state]
    public bool $witnessSelected = false;

    public function mount()
    {
        $this->challenger_id = Auth::id();
        $this->loadAgents();
    }

    // New computed property to check if user meets all requirements
    #[Computed]
    public function canCreateChallenge()
    {
        $currentUser = Auth::user();
        if (!$currentUser->discord_id || empty($currentUser->discord_id)) {
            return false;
        }

        return $this->availableTeams->isNotEmpty();
    }

    #[Computed]
    public function availableTeams()
    {
        $currentUser = Auth::user();

        return \DB::table('teams')
            ->join('team_user', 'teams.id', '=', 'team_user.team_id')
            ->where('team_user.user_id', $currentUser->id)
            ->where('team_user.status', 'approved')
            ->select('teams.id', 'teams.name', 'teams.discord_team_id')
            ->get();
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
        } finally {
            $this->loadingAgents = false;
        }
    }

    #[Computed]
    public function availableOpponents()
    {
        if (!$this->selected_team_id) {
            return collect();
        }

        $challengerTeamUser = \DB::table('team_user')
            ->where('team_id', $this->selected_team_id)
            ->where('user_id', $this->challenger_id)
            ->first();

        if (!$challengerTeamUser) {
            return collect();
        }

        return User::query()
            ->join('team_user', 'users.id', '=', 'team_user.user_id')
            ->where('team_user.team_id', $this->selected_team_id)
            ->where('users.id', '!=', $this->challenger_id)
            ->where('team_user.status', 'approved')
            ->whereNotNull('users.discord_id')
            ->where('users.discord_id', '!=', '')
            ->select('users.*', 'team_user.rank')
            ->whereBetween('team_user.rank', [$challengerTeamUser->rank - 1, $challengerTeamUser->rank + 1])
            ->orderBy('team_user.rank')
            ->get();
    }

    #[Computed]
    public function availableWitnesses()
    {
        if (!$this->selected_team_id || !$this->opponent_id) {
            return collect();
        }

        return User::query()
            ->join('team_user', 'users.id', '=', 'team_user.user_id')
            ->where('team_user.team_id', $this->selected_team_id)
            ->where('users.id', '!=', $this->challenger_id)
            ->where('users.id', '!=', $this->opponent_id)
            ->where('team_user.status', 'approved')
            ->whereNotNull('users.discord_id')
            ->where('users.discord_id', '!=', '')
            ->select('users.*')
            ->get();
    }

    #[Computed]
    public function selectedBannedAgent()
    {
        if (empty($this->banned_agent)) {
            return null;
        }
        return collect($this->agentsList)->firstWhere('uuid', $this->banned_agent);
    }

    public function confirmChallenge()
    {
        if ($this->step == 1 && $this->selected_team_id) {
            $this->step = 2;
        } elseif ($this->step == 2 && $this->opponent_id) {
            $this->step = 3;
        }
    }

    public function backToSelection()
    {
        if ($this->step == 3) {
            $this->step = 2;
        } elseif ($this->step == 2) {
            $this->step = 1;
        }
    }

    public function createChallenge()
    {
        // First, perform validation on the request data
        $this->validate([
            'selected_team_id' => 'required|exists:teams,id',
            'opponent_id' => 'required|exists:users,id',
            'witness_id' => 'required|exists:users,id',
            'banned_agent' => 'nullable|string',
        ]);

        // Then, perform business logic checks
        if (!$this->canCreateChallenge()) {
            $this->dispatch('challenge-error', [
                'message' => 'You do not meet the requirements to create a challenge.'
            ]);
            return;
        }

        // Check for existing pending/accepted challenges
        $existingChallenge = Challenge::where('challenger_id', $this->challenger_id)
            ->where('opponent_id', $this->opponent_id)
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($existingChallenge) {
            $opponent = User::find($this->opponent_id);
            $this->dispatch('challenge-exists', [
                'status' => $existingChallenge->status,
                'message' => 'A challenge has already been sent to ' . ($opponent ? $opponent->discord_id : 'this user') . ' with status: **' . $existingChallenge->status . '**.',
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
            'team_id' => $this->selected_team_id,
        ]);

        $this->sendToDiscord($challenge);

        $this->loading = false;
        $this->dispatch('challenge-created');
        $this->resetState();
    }

    protected function resetState()
    {
        $this->reset(['opponent_id', 'witness_id', 'banned_agent']);
        $this->step = 1;
        $this->opponentSelected = false;
        $this->witnessSelected = false;
    }

    protected function sendToDiscord($challenge)
    {
        if (class_exists('App\Services\DiscordService')) {
            app(\App\Services\DiscordService::class)->sendChallengeNotification($challenge, 'created');
        }
    }

    public function updatedOpponentId()
    {
        $this->opponentSelected = $this->opponent_id !== null;
        $this->witness_id = null;
        $this->witnessSelected = false;
    }

    public function updatedWitnessId()
    {
        $this->witnessSelected = $this->witness_id !== null;
    }

    public function updatedSelectedTeamId()
    {
        $this->opponent_id = null;
        $this->witness_id = null;
        $this->opponentSelected = false;
        $this->witnessSelected = false;
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
    setTimeout(() => showError = false, 5000);
});
$wire.on('challenge-error', (data) => {
    showError = true;
    errorMessage = data.message;
    setTimeout(() => showError = false, 5000);
});" class="container mt-1 bg-white rounded-lg shadow dark:bg-gray-800">

    <h1 class="p-5 text-xl font-bold text-gray-900 dark:text-white">Create New Challenge</h1>
    <div class="p-5 mx-auto">
        <button class="px-4 py-2 font-semibold text-white transition bg-blue-600 rounded hover:bg-blue-700"
            wire:click="$set('step', 1)">
            New Challenge
        </button>

        <div x-show="showSuccess" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="p-4 mt-4 text-sm text-white bg-green-500 rounded-lg">
            Challenge created successfully!
        </div>

        <div x-show="showError" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="p-4 mt-4 text-sm text-white bg-red-500 rounded-lg"
            x-html="errorMessage">
        </div>

        @if ($step == 1)
            <div class="mt-3">
                <h4 class="text-lg font-bold text-gray-800 dark:text-white">Select a Team</h4>
                @if (!$this->canCreateChallenge)
                    <div class="p-4 mt-4 text-sm text-white bg-red-500 rounded-lg">
                        <p><strong>You cannot create challenges because:</strong></p>
                        <ul class="mt-2 ml-4 list-disc">
                            @if (!Auth::user()->discord_id)
                                <li>You must have a Discord ID linked to your account</li>
                            @endif
                            @if ($this->availableTeams->isEmpty())
                                <li>You must be an approved member of at least one team</li>
                            @endif
                        </ul>
                        <p class="mt-2">Please contact an administrator to resolve these issues.</p>
                    </div>
                @else
                    <div class="mb-3">
                        <label for="selected_team_id" class="block text-gray-700 dark:text-gray-300">Available Teams</label>
                        <select id="selected_team_id"
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:bg-gray-700 dark:text-white"
                            wire:model.live="selected_team_id">
                            <option value="">Choose a team</option>
                            @foreach ($this->availableTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($selected_team_id)
                        <button
                            class="px-4 py-2 font-semibold text-white transition bg-blue-600 rounded hover:bg-blue-700"
                            wire:click.prevent="confirmChallenge">
                            Next: Select Opponent
                        </button>
                    @endif
                @endif
            </div>
        @endif

        @if ($step == 2)
            <div class="mt-3">
                <h4 class="text-lg font-bold text-gray-800 dark:text-white">Select an Opponent</h4>
                @if ($this->availableOpponents->isEmpty())
                    <p class="text-red-500">No opponents available to challenge.</p>
                    <button
                        class="px-4 py-2 mt-2 font-semibold text-gray-700 transition bg-gray-200 rounded hover:bg-gray-300"
                        wire:click.prevent="backToSelection">
                        Back to Team Selection
                    </button>
                @else
                    <div class="mb-3">
                        <label for="opponent_id" class="block text-gray-700 dark:text-gray-300">Available
                            Opponents</label>
                        <select id="opponent_id"
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:bg-gray-700 dark:text-white"
                            wire:model.live="opponent_id">
                            <option value="">Choose an opponent</option>
                            @foreach ($this->availableOpponents as $player)
                                <option value="{{ $player->id }}">{{ $player->name }} (Rank: {{ $player->rank }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-2">
                        @if ($opponentSelected)
                            <button
                                class="px-4 py-2 font-semibold text-white transition bg-blue-600 rounded hover:bg-blue-700"
                                wire:click.prevent="confirmChallenge">
                                Next: Confirm Challenge
                            </button>
                        @endif

                        <button
                            class="px-4 py-2 font-semibold text-gray-700 transition bg-gray-200 rounded hover:bg-gray-300"
                            wire:click.prevent="backToSelection">
                            Back
                        </button>
                    </div>
                @endif
            </div>
        @endif

        @if ($step == 3)
            <div class="mt-3">
                <h4 class="text-lg font-bold text-gray-800 dark:text-white">Confirm Challenge</h4>
                <p class="text-gray-600 dark:text-gray-400">Challenger: <strong>{{ Auth::user()->name }}</strong></p>
                <p class="text-gray-600 dark:text-gray-400">Opponent:
                    <strong>{{ $opponent_id ? User::find($opponent_id)?->name : 'Not selected' }}</strong>
                </p>

                <div class="mb-3">
                    <label for="witness_id" class="block text-gray-700 dark:text-gray-300">Witness</label>
                    <select id="witness_id"
                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 dark:bg-gray-700 dark:text-white"
                        wire:model.live="witness_id">
                        <option value="">Select Witness</option>
                        @foreach ($this->availableWitnesses as $player)
                            <option value="{{ $player->id }}">{{ $player->name }}</option>
                        @endforeach
                    </select>
                </div>

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
                                <div class="relative transition-transform cursor-pointer hover:-translate-y-1"
                                    wire:click="$set('banned_agent', '{{ $agentData }}')">
                                    <div
                                        class="flex flex-col items-center p-2 transition-all border-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700
                                        {{ $isSelected ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-transparent' }}">
                                        <img src="{{ $agent['displayIconSmall'] }}" alt="{{ $agent['displayName'] }}"
                                            class="w-16 h-16 rounded-full"
                                            onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCA2NCA2NCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3J       nLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0IiByeD0iMzIiIGZpbGw9IiNGM0Y0RjYiLz4KPHN2ZyB4PSIxNiIgeT0iMTYiIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiB4bWxuc       z0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMTIgMTJDMTQuMjA5MSAxMiAxNiAxMC4yMDkxIDE2IDhDMTYgNS43OTA5IDE0LjIwOTEgNCA1VjhDNCA5LjEgNC45IDEwIDYgMTBIMThDMTkuMSAxMCAyMCA5LjEgMjAgO       FYxOEMxOS4xIDEwIDE4IDEwIDE3IDEwSDdDNiAxMCA1IDEwLjkgNSAxMloiIGZpbGw9IiM5Q0EzQUYiLz4KPC9zdmc+Cjwvc3ZnPgo='">
                                        <span class="mt-1 text-sm text-center">{{ $agent['displayName'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex gap-4">
                    <button
                        class="px-4 py-2 font-semibold text-white transition rounded disabled:opacity-50 disabled:cursor-not-allowed
                        {{ $witnessSelected ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400' }}"
                        wire:click.prevent="createChallenge" wire:loading.attr="disabled"
                        @if (!$witnessSelected) disabled @endif>
                        <span wire:loading.remove wire:target="createChallenge">Confirm Challenge</span>
                        <span wire:loading wire:target="createChallenge">Creating...</span>
                    </button>

                    <button
                        class="px-4 py-2 font-semibold text-gray-700 transition bg-gray-200 rounded hover:bg-gray-300"
                        wire:click.prevent="backToSelection">
                        Back
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>