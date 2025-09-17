<?php

use Livewire\Volt\Component;
use App\Models\Team;
use App\Models\RankHistory;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $teams;
    public $selectedTeamId;
    public $rankHistory;

    public function mount()
    {
        $this->teams = Auth::user()->teams;

        if ($this->teams->isEmpty()) {
            $this->rankHistory = collect();
            return;
        }

        $this->selectedTeamId = $this->teams->first()->id;
        $this->loadRankHistory();
    }

    public function switchTeam($teamId)
    {
        $this->selectedTeamId = $teamId;
        $this->loadRankHistory();
    }

    public function loadRankHistory()
    {
        if (!$this->selectedTeamId) {
            $this->rankHistory = collect();
            return;
        }

        $this->rankHistory = RankHistory::with(['team', 'challenge.challenger', 'challenge.opponent'])
            ->where('user_id', Auth::id())
            ->where('team_id', $this->selectedTeamId)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($history) {
                $challenge = $history->challenge;

                if ($challenge) {
                    $opponent = $challenge->challenger_id === Auth::id()
                        ? $challenge->opponent
                        : $challenge->challenger;
                } else {
                    $opponent = null;
                }

                return (object) [
                    'from_rank'   => $history->previous_rank,
                    'to_rank'     => $history->new_rank,
                    'created_at'  => $history->created_at,
                    'team_name'   => $history->team?->name,
                    'opponent'    => $opponent?->name ?? 'N/A',
                ];
            });
    }
};

?>

<!-- View -->
<div class="container p-4 mx-auto lg:p-6">
    <div class="overflow-hidden bg-gray-900 shadow-2xl rounded-2xl">
        <!-- Header -->
        <div class="p-6 border-b border-gray-700 bg-gradient-to-r from-green-600 to-blue-600">
            <h1 class="flex items-center text-2xl font-extrabold text-white">
                📊 Rank History
            </h1>
        </div>

        <!-- Team Tabs -->
        <div class="px-6 py-3 bg-gray-800 border-b border-gray-700">
            <div class="flex flex-wrap gap-3">
                @foreach ($teams as $team)
                    <button @class([
                        'px-4 py-2 rounded-xl font-semibold transition',
                        'bg-gradient-to-r from-green-500 to-blue-500 text-white shadow-lg scale-105' => $selectedTeamId === $team->id,
                        'bg-gray-700 text-gray-300 hover:bg-gray-600 hover:text-white' => $selectedTeamId !== $team->id,
                    ]) wire:click="switchTeam({{ $team->id }})">
                        {{ $team->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Rank History Table -->
        <div class="overflow-x-auto">
            @if ($rankHistory->isEmpty())
                <div class="flex flex-col items-center justify-center py-12">
                    <p class="mt-4 text-lg text-gray-400">No rank history found for this team</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Date</th>
                            <th class="px-3 py-2 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Team</th>
                            <th class="px-3 py-2 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Opponent</th>
                            <th class="px-3 py-2 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">From</th>
                            <th class="px-3 py-2 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">To</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach ($rankHistory as $history)
                            <tr class="transition hover:bg-gray-800/70">
                                <td class="px-3 py-2 text-gray-300">
                                    {{ $history->created_at->diffForHumans() }}
                                </td>
                                <td class="px-3 py-2 font-semibold text-blue-400">{{ $history->team_name }}</td>
                                <td class="px-3 py-2 font-semibold text-purple-400">{{ $history->opponent }}</td>
                                <td class="px-3 py-2 text-red-400">#{{ $history->from_rank }}</td>
                                <td class="px-3 py-2 text-green-400">#{{ $history->to_rank }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
