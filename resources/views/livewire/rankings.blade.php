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

<div class="container p-2 mx-auto sm:p-4 lg:p-6">
    <div class="overflow-hidden shadow-2xl bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl">
        <!-- Header with Gradient Overlay -->
        <div class="relative p-6 border-b border-gray-700/50">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-600/20 to-purple-600/20"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <h1 class="flex items-center text-2xl font-bold text-white">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Rankings
                        <span class="ml-2 text-sm font-normal text-gray-400">
                            ({{ $teamRankings->count() }} players)
                        </span>
                    </h1>
                </div>
            </div>
        </div>

        <!-- Team Tabs with Animated Hover -->
        <div class="px-6 py-3 border-b border-gray-700/50 bg-gray-800/50">
            <div class="flex space-x-4">
                @foreach ($teams as $team)
                    <button @class([
                        'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200',
                        'bg-blue-600 text-white shadow-lg' => $selectedTeamId === $team->id,
                        'text-gray-400 hover:text-white hover:bg-gray-700/50' => $selectedTeamId !== $team->id,
                    ]) wire:click="switchTeam({{ $team->id }})">
                        {{ $team->name }}
                    </button>
                @endforeach
            </div>
        </div>

    <!-- Rankings List as Responsive Table with Styles and Icons -->
    <div class="overflow-x-auto">
        @if ($teamRankings->isEmpty())
            <div class="flex flex-col items-center justify-center py-12">
                <svg class="w-16 h-16 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                </svg>
                <p class="mt-4 text-lg text-gray-400">No ranked players found</p>
            </div>
        @else
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">#</th>
                        <th class="px-3 py-2 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Name</th>
                        <th class="px-3 py-2 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Alias</th>
                        <th class="px-3 py-2 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Rank</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 bg-gradient-to-br from-gray-900 to-gray-800">
                    @foreach ($teamRankings as $index => $user)
                        <tr class="transition hover:bg-gray-800/70 group">
                            <!-- Rank Number with Special Styling for Top 3 -->
                            <td class="px-3 py-2 font-bold text-center whitespace-nowrap">
                                @if ($index === 0)
                                    <span class="inline-flex items-center justify-center w-10 h-10 text-xl text-white transition-transform rounded-lg bg-gradient-to-br from-yellow-400 to-blue-600 group-hover:scale-110">👑</span>
                                @elseif ($index === 1)
                                    <span class="inline-flex items-center justify-center w-10 h-10 text-xl text-white transition-transform rounded-lg bg-gradient-to-br from-gray-400 to-gray-600 group-hover:scale-110">2</span>
                                @elseif ($index === 2)
                                    <span class="inline-flex items-center justify-center w-10 h-10 text-xl text-white transition-transform rounded-lg bg-gradient-to-br from-amber-700 to-amber-900 group-hover:scale-110">3</span>
                                @else
                                    <span class="text-lg font-medium text-gray-400">{{ $index + 1 }}</span>
                                @endif
                            </td>
                            <!-- Enhanced User Info -->
                            <td class="px-3 py-2 text-white whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex items-center justify-center w-12 h-12 mr-2 transition-transform rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 group-hover:scale-105">
                                        <span class="text-lg font-bold text-white">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-bold text-white transition-colors duration-200 group-hover:text-blue-400">
                                            {{ $user->name }}
                                        </h2>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-gray-300 whitespace-nowrap">
                                @if ($user->alias)
                                    {{ $user->alias }}
                                @else
                                    <span class="italic text-gray-500">No alias set</span>
                                @endif
                            </td>
                            <!-- Animated Rank Badge -->
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold
                                    {{ $index < 3 ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white' : 'bg-gray-700 text-gray-300' }}
                                    group-hover:scale-105 group-hover:shadow-lg transition-all duration-200">
                                    Rank {{ $user->rank }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    </div>

    <!-- Enhanced Refresh Button -->
    <div class="flex justify-end mt-6">
    <button wire:click="loadTeamRankings"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-50 cursor-not-allowed"
            class="inline-flex items-center px-6 py-3 text-sm font-semibold text-white transition-all duration-200 rounded-lg bg-gradient-to-r from-blue-600 to-purple-600 hover:shadow-lg hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
        <span wire:loading.remove class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Refresh Rankings
        </span>
        {{-- <span wire:loading wire:target="loadTeamRankings" class="flex items-center">
            <svg class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Updating...
        </span> --}}
    </button>
</div>
</div>
