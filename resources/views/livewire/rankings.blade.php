<?php

use Livewire\Volt\Component;
use App\Models\User;
use function Livewire\Volt\{state};

new class extends Component {
    public $teams; // Teams the user belongs to
    public $selectedTeamId; // Selected team ID
    public $teamRankings; // Rankings for selected team

    public function mount()
    {
        // Fetch teams the current user belongs to
        $this->teams = auth()->user()->teams;

        // Check if the user has no teams and handle that case
        if ($this->teams->isEmpty()) {
            $this->teamRankings = collect(); // Empty collection for rankings
            return; // Optionally, show a message or handle UI accordingly
        }

        // Set the default team and load its rankings
        $this->selectedTeamId = $this->teams->first()->id;
        $this->loadTeamRankings(); // Load rankings for the first team
    }

    // Load rankings for the selected team
    public function loadTeamRankings()
    {
        if ($this->selectedTeamId) {
            $this->teamRankings = User::select('users.*')
                ->join('team_user', 'users.id', '=', 'team_user.user_id')
                ->where('team_user.team_id', $this->selectedTeamId)
                ->orderBy('team_user.rank', 'asc')
                ->with([
                    'teams' => function ($query) {
                        $query->where('teams.id', $this->selectedTeamId);
                    },
                ])
                ->get()
                ->map(function ($user) {
                    $user->rank = $user->teams->firstWhere('id', $this->selectedTeamId)->pivot->rank;
                    return $user;
                });
        }
    }

    // Handle team switch (update selected team and load rankings)
    public function switchTeam($teamId)
    {
        $this->selectedTeamId = $teamId;
        $this->loadTeamRankings();
    }
};

?>

<div class="container p-4 mx-auto sm:p-4 lg:p-6">
    <div class="overflow-hidden bg-white rounded-lg shadow-lg dark:bg-gray-800">
        <!-- Header -->
        <div class="p-4 border-b border-gray-200 sm:p-6 dark:border-gray-700">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Rankings
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    ({{ $teamRankings->count() }} players)
                </span>
            </h1>
        </div>

        <!-- Team Tabs -->
        <div class="px-4 py-2 border-b border-gray-200 sm:px-6 dark:border-gray-700">
            <div class="flex space-x-4">
                @foreach ($teams as $team)
                    <button @class([
                        'px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-200 hover:text-gray-900 dark:hover:text-white',
                        'border-b-2 border-blue-500' => $selectedTeamId === $team->id,
                        'border-transparent' => $selectedTeamId !== $team->id,
                    ]) wire:click="switchTeam({{ $team->id }})">
                        {{ $team->name }}
                    </button>
                @endforeach
            </div>
        </div>


        <!-- Rankings List -->
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @if ($teamRankings->isEmpty())
                <div class="flex flex-col items-center justify-center py-12">
                    <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                    </svg>
                    <p class="mt-4 text-lg text-gray-500 dark:text-gray-400">No ranked players found</p>
                </div>
            @else
                @foreach ($teamRankings as $index => $user)
                    <div
                        class="relative transition-colors duration-150 ease-in-out hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <div class="flex items-center px-2 py-4 space-x-4 sm:px-4 lg:px-6">
                            <!-- Rank Number -->
                            <div class="flex-shrink-0 w-12 text-center">
                                @if ($index < 3)
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full
                                        {{ $index === 0 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100' : '' }}
                                        {{ $index === 1 ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100' : '' }}
                                        {{ $index === 2 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100' : '' }}">
                                        {{ $index + 1 }}
                                    </span>
                                @else
                                    <span class="font-medium text-gray-600 dark:text-gray-400">
                                        {{ $index + 1 }}
                                    </span>
                                @endif
                            </div>

                            <!-- User Info -->
                            <div class="flex-grow ml-2">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div
                                            class="flex items-center justify-center w-10 h-10 bg-gray-200 rounded-full dark:bg-gray-700">
                                            <span class="text-lg font-medium text-gray-600 dark:text-gray-300">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ $user->name }}
                                        </h2>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            @if ($user->alias)
                                                Alias: {{ $user->alias }}
                                            @else
                                                No alias set
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Rank Badge aligned to the right -->
                            <div class="flex-shrink-0 ml-auto">
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                    {{ $index < 3 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100' }}">
                                    Rank #{{ $user->rank }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <!-- Refresh Button with Loading State -->
    <div class="flex justify-end mt-4">
        <button wire:click="loadTeamRankings" wire:loading.attr="disabled"
            wire:loading.class="opacity-50 cursor-not-allowed"
            class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out bg-gray-800 border border-transparent rounded-md dark:bg-gray-200 dark:text-gray-800 hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">

            <!-- Loading Spinner (only shows during loading state) -->
            <svg wire:loading.class="animate-spin" wire:loading class="hidden w-4 h-4 mr-2" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                </path>
            </svg>

            <!-- Default Button Text -->
            <span wire:loading.remove>
                Refresh Rankings
            </span>

            <!-- Loading Text -->
            {{-- <span wire:loading>
                Loading...
            </span> --}}
        </button>
    </div>

</div>
