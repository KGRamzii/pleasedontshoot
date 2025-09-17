<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

new class extends Component {
    public $teams;
    public $selectedTeamId;
    public $teamRankings;

    public function mount()
    {
        $this->teams = auth()->user()->teams;

        if ($this->teams->isEmpty()) {
            $this->teamRankings = collect();
            return;
        }

        $this->selectedTeamId = $this->teams->first()->id;
        $this->loadTeamRankings();
    }

    public function loadTeamRankings()
    {
        if (!$this->selectedTeamId) {
            $this->teamRankings = collect();
            return;
        }

        // Cache per team for faster cold start
        $cacheKey = "team_rankings:{$this->selectedTeamId}";

        $this->teamRankings = Cache::remember($cacheKey, now()->addMinutes(5), function () {
            return DB::table('users')
                ->join('team_user', 'users.id', '=', 'team_user.user_id')
                ->where('team_user.team_id', $this->selectedTeamId)
                ->orderBy('team_user.rank', 'asc')
                ->select('users.id', 'users.name', 'users.alias', 'team_user.rank')
                ->get();
        });
    }

    public function switchTeam($teamId)
    {
        $this->selectedTeamId = $teamId;
        $this->loadTeamRankings();
    }
};
?>

<div class="container p-4 mx-auto lg:p-6">
    <div class="overflow-hidden bg-gray-900 shadow-2xl rounded-2xl">
        <!-- Header -->
        <div class="p-6 border-b border-gray-700 bg-gradient-to-r from-blue-600 to-purple-600">
            <h1 class="flex items-center text-3xl font-extrabold text-white">
                🏆 Team Rankings
                <span class="ml-3 text-base font-normal text-gray-200">
                    ({{ $teamRankings->count() }} players)
                </span>
            </h1>
        </div>

        <!-- Team Tabs -->
        <div class="px-6 py-3 bg-gray-800 border-b border-gray-700">
            <div class="flex flex-wrap gap-3">
                @foreach ($teams as $team)
                    <button @class([
                        'px-4 py-2 rounded-xl font-semibold transition',
                        'bg-gradient-to-r from-blue-500 to-purple-500 text-white shadow-lg scale-105' => $selectedTeamId === $team->id,
                        'bg-gray-700 text-gray-300 hover:bg-gray-600 hover:text-white' => $selectedTeamId !== $team->id,
                    ]) wire:click="switchTeam({{ $team->id }})">
                        {{ $team->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Rankings Table -->
        <div class="overflow-x-auto">
            @if ($teamRankings->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                    <span class="text-5xl">😔</span>
                    <p class="mt-4 text-lg">No ranked players yet</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="text-gray-200 bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-sm font-bold text-left uppercase">Rank</th>
                            <th class="px-4 py-3 text-sm font-bold text-left uppercase">Player</th>
                            <th class="px-4 py-3 text-sm font-bold text-left uppercase">Alias</th>
                            <th class="px-4 py-3 text-sm font-bold text-left uppercase">Points</th>
                        </tr>
                    </thead>
                    <tbody class="bg-gray-900 divide-y divide-gray-800">
                        @foreach ($teamRankings as $index => $user)
                            <tr class="transition hover:bg-gray-800">
                                <!-- Rank with Medal -->
                                <td class="px-4 py-3 text-center">
                                    @if ($index === 0)
                                        <span class="px-3 py-1 font-bold text-black bg-yellow-500 rounded-full">🥇 1</span>
                                    @elseif ($index === 1)
                                        <span class="px-3 py-1 font-bold text-black bg-gray-400 rounded-full">🥈 2</span>
                                    @elseif ($index === 2)
                                        <span class="px-3 py-1 font-bold text-white rounded-full bg-amber-700">🥉 3</span>
                                    @else
                                        <span class="text-lg font-semibold text-gray-300">{{ $index + 1 }}</span>
                                    @endif
                                </td>

                                <!-- Player Info -->
                                <td class="flex items-center gap-3 px-4 py-3">
                                    <div class="flex items-center justify-center w-12 h-12 text-lg font-bold text-white rounded-full bg-gradient-to-r from-blue-500 to-purple-600">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <span class="text-lg font-semibold text-white">{{ $user->name }}</span>
                                </td>

                                <!-- Alias -->
                                <td class="px-4 py-3 text-gray-300">
                                    {{ $user->alias ?? '—' }}
                                </td>

                                <!-- Rank Number -->
                                <td class="px-4 py-3">
                                    <span class="px-4 py-2 text-sm font-bold text-white rounded-lg bg-gradient-to-r from-blue-600 to-purple-600">
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
</div>
