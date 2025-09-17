<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Team;
use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\RankHistory;

new class extends Component {
    public $topRankedUsers;
    public $recentMatches;
    public $totalPlayers = 0;
    public $totalMatches = 0;
    public $activeTeams = 0;

    public function mount()
    {
        // Load all data in a single optimized method
        $this->loadAllData();
    }

    public function loadAllData()
    {
        $user = Auth::user();
        $userTeam = null;

        // Get user's team efficiently if authenticated
        if ($user) {
            $userTeam = Cache::remember("user_team_{$user->id}", 300, function () use ($user) {
                return $user->teams()
                    ->select('teams.id', 'teams.name')
                    ->first();
            });
        }

        // Load all data with a single cache operation
        $cacheData = Cache::remember('homepage_data', 300, function () use ($userTeam) {
            $data = [];

            // Load stats in a single query
            $stats = DB::select("
                SELECT
                    (SELECT COUNT(*) FROM users) as total_players,
                    (SELECT COUNT(*) FROM challenges WHERE status = 'completed') as total_matches,
                    (SELECT COUNT(*) FROM teams) as active_teams
            ")[0];

            $data['stats'] = [
                'totalPlayers' => $stats->total_players,
                'totalMatches' => $stats->total_matches,
                'activeTeams' => $stats->active_teams
            ];

            // Load top ranked users for the team (if user has a team)
            if ($userTeam) {
                $data['topRankedUsers'] = DB::table('team_user')
                    ->join('users', 'team_user.user_id', '=', 'users.id')
                    ->where('team_user.team_id', $userTeam->id)
                    ->where('team_user.status', 'approved')
                    ->select('users.id', 'users.name', 'team_user.rank')
                    ->orderBy('team_user.rank', 'asc')
                    ->limit(3)
                    ->get()
                    ->map(function ($user) {
                        return (object) [
                            'id' => $user->id,
                            'name' => $user->name,
                            'pivot' => (object) ['rank' => $user->rank]
                        ];
                    });
            } else {
                $data['topRankedUsers'] = collect();
            }

            // Load recent matches with all related data in optimized query
            $recentChallenges = DB::table('challenges as c')
                ->join('users as challenger', 'c.challenger_id', '=', 'challenger.id')
                ->join('users as opponent', 'c.opponent_id', '=', 'opponent.id')
                ->join('users as witness', 'c.witness_id', '=', 'witness.id')
                ->where('c.status', 'completed')
                ->select([
                    'c.id',
                    'c.challenger_id',
                    'c.opponent_id',
                    'c.witness_id',
                    'c.updated_at',
                    'challenger.name as challenger_name',
                    'opponent.name as opponent_name',
                    'witness.name as witness_name'
                ])
                ->orderBy('c.updated_at', 'desc')
                ->limit(5)
                ->get();

            // Get all rank histories for these challenges in one query
            $challengeIds = $recentChallenges->pluck('id');
            $rankHistories = collect();

            if ($challengeIds->isNotEmpty()) {
                $rankHistories = DB::table('rank_histories')
                    ->whereIn('challenge_id', $challengeIds)
                    ->select('challenge_id', 'user_id', 'previous_rank', 'new_rank')
                    ->get()
                    ->groupBy('challenge_id');
            }

            // Process matches with rank data
            $data['recentMatches'] = $recentChallenges->map(function ($challenge) use ($rankHistories) {
                $match = (object) [
                    'id' => $challenge->id,
                    'challenger' => (object) ['name' => $challenge->challenger_name],
                    'opponent' => (object) ['name' => $challenge->opponent_name],
                    'witness' => (object) ['name' => $challenge->witness_name],
                    'updated_at' => $challenge->updated_at,
                    'winner' => null,
                    'loser' => null,
                    'winner_rank_change' => null,
                    'loser_rank_change' => null
                ];

                // Process rank histories for this challenge
                $histories = $rankHistories->get($challenge->id, collect());

                // Check if ranks were actually swapped by comparing both players
                if ($histories->count() === 2) {
                    $history1 = $histories->first();
                    $history2 = $histories->last();

                    // Determine winner based on who took the better rank position
                    $winner_history = null;
                    $loser_history = null;

                    // If player 1's new rank is better (lower number) than player 2's new rank
                    if ($history1->new_rank < $history2->new_rank) {
                        $winner_history = $history1;
                        $loser_history = $history2;
                    } else if ($history2->new_rank < $history1->new_rank) {
                        $winner_history = $history2;
                        $loser_history = $history1;
                    }
                    // If new ranks are equal, check who improved more from their previous rank
                    else if ($history1->new_rank === $history2->new_rank) {
                        // Compare improvement (previous_rank - new_rank, higher = more improvement)
                        $improvement1 = $history1->previous_rank - $history1->new_rank;
                        $improvement2 = $history2->previous_rank - $history2->new_rank;

                        if ($improvement1 > $improvement2) {
                            $winner_history = $history1;
                            $loser_history = $history2;
                        } else if ($improvement2 > $improvement1) {
                            $winner_history = $history2;
                            $loser_history = $history1;
                        }
                        // If equal improvement, it's a tie - no clear winner
                    }

                    if ($winner_history && $loser_history) {
                        // Set winner
                        $winnerName = $winner_history->user_id == $challenge->challenger_id ?
                            $challenge->challenger_name : $challenge->opponent_name;
                        $match->winner = (object) ['name' => $winnerName];
                        $match->winner_rank_change = [
                            'from' => $winner_history->previous_rank,
                            'to' => $winner_history->new_rank,
                            'movement' => $winner_history->previous_rank > $winner_history->new_rank ? 'up' : 'down'
                        ];

                        // Set loser
                        $loserName = $loser_history->user_id == $challenge->challenger_id ?
                            $challenge->challenger_name : $challenge->opponent_name;
                        $match->loser = (object) ['name' => $loserName];
                        $match->loser_rank_change = [
                            'from' => $loser_history->previous_rank,
                            'to' => $loser_history->new_rank,
                            'movement' => $loser_history->previous_rank > $loser_history->new_rank ? 'up' : 'down'
                        ];
                    }
                } else {
                    // Fallback for single history record or unexpected data
                    foreach ($histories as $history) {
                        $rankChange = [
                            'from' => $history->previous_rank,
                            'to' => $history->new_rank,
                            'movement' => $history->previous_rank > $history->new_rank ? 'up' : 'down'
                        ];

                        $userName = $history->user_id == $challenge->challenger_id ?
                            $challenge->challenger_name : $challenge->opponent_name;

                        // If rank improved, likely winner; if worse, likely loser
                        if ($history->previous_rank > $history->new_rank) {
                            $match->winner = (object) ['name' => $userName];
                            $match->winner_rank_change = $rankChange;
                        } else if ($history->previous_rank < $history->new_rank) {
                            $match->loser = (object) ['name' => $userName];
                            $match->loser_rank_change = $rankChange;
                        }
                    }
                }

                return $match;
            });

            return $data;
        });

        // Assign cached data to component properties
        $this->totalPlayers = $cacheData['stats']['totalPlayers'];
        $this->totalMatches = $cacheData['stats']['totalMatches'];
        $this->activeTeams = $cacheData['stats']['activeTeams'];
        $this->topRankedUsers = $cacheData['topRankedUsers'];
        $this->recentMatches = $cacheData['recentMatches'];

        \Log::info('Homepage data loaded from cache', [
            'top_users_count' => $this->topRankedUsers->count(),
            'recent_matches_count' => $this->recentMatches->count(),
        ]);
    }

    // Method to refresh data (useful for testing or manual refresh)
    public function refreshData()
    {
        Cache::forget('homepage_data');
        if (Auth::check()) {
            Cache::forget("user_team_" . Auth::id());
        }
        $this->loadAllData();
    }
}; ?>

<div class="min-h-screen bg-gray-900">
    <!-- Hero Section -->
    <div class="relative overflow-hidden bg-gradient-to-br from-gray-900 via-blue-900 to-purple-900">
        <!-- Animated Background Pattern -->
        <div class="absolute inset-0 opacity-20">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-purple-500/10"></div>
            <div class="absolute top-0 left-0 w-full h-full">
                <div class="absolute w-2 h-2 bg-blue-400 rounded-full top-1/4 left-1/4 animate-pulse"></div>
                <div class="absolute w-1 h-1 bg-purple-400 rounded-full top-1/3 right-1/3 animate-ping"></div>
                <div class="absolute w-3 h-3 bg-pink-400 rounded-full bottom-1/4 right-1/4 animate-pulse"></div>
                <div class="absolute w-1 h-1 bg-blue-300 rounded-full bottom-1/3 left-1/3 animate-ping"></div>
            </div>
        </div>

        <!-- Hero Background Image -->
        <div class="absolute inset-0 bg-black/40"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 via-transparent to-transparent"></div>

        <div class="relative px-4 py-20 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-32">
            <div class="text-center">
                <!-- Main Title -->
                <div class="mb-8">
                    <h1 class="text-4xl font-black leading-tight tracking-tight text-transparent sm:text-5xl md:text-6xl lg:text-7xl bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-blue-500">
                        VALORANT
                    </h1>
                    <div class="mt-2 text-3xl font-bold text-white sm:text-4xl md:text-5xl lg:text-6xl">
                        Pink Slip <span class="text-pink-500">Challenge</span>
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-blue-400 sm:text-3xl md:text-4xl">
                        Climb • Compete • Conquer
                    </div>
                </div>

                <!-- Subtitle -->
                <p class="max-w-3xl mx-auto mt-6 text-lg leading-relaxed text-gray-300 sm:text-xl md:text-2xl">
                    The ultimate competitive ranking system for Valorant players. Challenge opponents, climb the ladder,
                    and prove you're the best in your community.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col justify-center gap-4 mt-10 sm:flex-row sm:gap-6">
                    @auth
                        <a href="{{ route('dashboard') }}"
                            class="relative inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white transition-all duration-300 transform shadow-xl group bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl hover:from-blue-700 hover:to-purple-700 hover:scale-105 hover:shadow-2xl">
                            <span class="absolute inset-0 transition-opacity bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl blur-lg opacity-30 group-hover:opacity-50"></span>
                            <span class="relative">Enter Dashboard</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="relative inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white transition-all duration-300 transform shadow-xl group bg-gradient-to-r from-pink-600 to-purple-600 rounded-xl hover:from-pink-700 hover:to-purple-700 hover:scale-105 hover:shadow-2xl">
                            <span class="absolute inset-0 transition-opacity bg-gradient-to-r from-pink-600 to-purple-600 rounded-xl blur-lg opacity-30 group-hover:opacity-50"></span>
                            <span class="relative">Sign In</span>
                        </a>
                        <a href="{{ route('register') }}"
                            class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-gray-200 transition-all duration-300 transform border-2 border-gray-600 bg-gray-800/50 backdrop-blur-sm rounded-xl hover:bg-gray-700/50 hover:border-gray-500 hover:scale-105">
                            Join the Fight
                        </a>
                    @endauth
                </div>

                <!-- Stats Bar -->
                <div class="grid max-w-2xl grid-cols-1 gap-6 mx-auto mt-16 sm:grid-cols-3">
                    <div class="p-6 border border-gray-700 rounded-lg bg-gray-800/50 backdrop-blur-sm">
                        <div class="text-3xl font-bold text-blue-400">{{ $totalPlayers }}+</div>
                        <div class="mt-1 text-gray-300">Active Players</div>
                    </div>
                    <div class="p-6 border border-gray-700 rounded-lg bg-gray-800/50 backdrop-blur-sm">
                        <div class="text-3xl font-bold text-purple-400">{{ $totalMatches }}+</div>
                        <div class="mt-1 text-gray-300">Matches Played</div>
                    </div>
                    <div class="p-6 border border-gray-700 rounded-lg bg-gray-800/50 backdrop-blur-sm">
                        <div class="text-3xl font-bold text-pink-400">{{ $activeTeams }}+</div>
                        <div class="mt-1 text-gray-300">Teams</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- How It Works Section -->
    <div class="py-20 bg-gray-800/30">
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-16 text-center">
                <h2 class="mb-4 text-4xl font-bold text-white">How It Works</h2>
                <p class="text-xl text-gray-400">Simple, fair, and competitive</p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                <div class="text-center group">
                    <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 transition-transform duration-300 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 group-hover:scale-110">
                        <span class="text-3xl font-bold text-white">1</span>
                    </div>
                    <h3 class="mb-4 text-2xl font-bold text-white">Challenge Players</h3>
                    <p class="leading-relaxed text-gray-400">Find opponents within your skill range and send them a challenge. Every match matters in the ranking system.</p>
                </div>

                <div class="text-center group">
                    <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 transition-transform duration-300 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 group-hover:scale-110">
                        <span class="text-3xl font-bold text-white">2</span>
                    </div>
                    <h3 class="mb-4 text-2xl font-bold text-white">Fair Competition</h3>
                    <p class="leading-relaxed text-gray-400">Every match is witnessed by a neutral third party to ensure fair play and accurate results reporting.</p>
                </div>

                <div class="text-center group">
                    <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 transition-transform duration-300 rounded-full bg-gradient-to-r from-pink-500 to-blue-500 group-hover:scale-110">
                        <span class="text-3xl font-bold text-white">3</span>
                    </div>
                    <h3 class="mb-4 text-2xl font-bold text-white">Climb the Ranks</h3>
                    <p class="leading-relaxed text-gray-400">Win matches to climb the ladder. Your ranking reflects your true skill level in the community.</p>
                </div>
            </div>
        </div>
    </div>

    @if ($topRankedUsers->count() > 0)
    <!-- Top Players Section -->
    <div class="py-20">
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-16 text-center">
                <h2 class="mb-4 text-4xl font-bold text-white">Hall of Champions</h2>
                <p class="text-xl text-gray-400">Meet our current top-ranked legends</p>
            </div>

            <div class="grid grid-cols-1 gap-8 sm:grid-cols-3">
                @foreach ($topRankedUsers as $index => $user)
                    <div class="relative group">
                        @if ($index === 0)
                            <!-- First Place - Special Styling -->
                            <div class="relative p-8 transition-all duration-500 transform border-2 bg-gradient-to-br from-yellow-400/20 to-yellow-600/20 rounded-2xl group-hover:scale-105 group-hover:rotate-1 border-yellow-500/30">
                                <div class="absolute flex items-center justify-center w-12 h-12 text-2xl rounded-full -top-4 -right-4 bg-gradient-to-r from-yellow-400 to-yellow-600 animate-pulse">
                                    👑
                                </div>
                        @elseif ($index === 1)
                            <!-- Second Place -->
                            <div class="relative p-8 transition-all duration-500 transform border-2 bg-gradient-to-br from-gray-300/20 to-gray-500/20 rounded-2xl group-hover:scale-105 group-hover:-rotate-1 border-gray-400/30">
                                <div class="absolute flex items-center justify-center w-12 h-12 text-2xl rounded-full -top-4 -right-4 bg-gradient-to-r from-gray-300 to-gray-500">
                                    🥈
                                </div>
                        @else
                            <!-- Third Place -->
                            <div class="relative p-8 transition-all duration-500 transform border-2 bg-gradient-to-br from-amber-600/20 to-amber-800/20 rounded-2xl group-hover:scale-105 group-hover:rotate-1 border-amber-600/30">
                                <div class="absolute flex items-center justify-center w-12 h-12 text-2xl rounded-full -top-4 -right-4 bg-gradient-to-r from-amber-600 to-amber-800">
                                    🥉
                                </div>
                        @endif

                                <!-- Player Avatar -->
                                <div class="flex justify-center mb-6">
                                    <div class="relative">
                                        <div class="flex items-center justify-center w-24 h-24 text-3xl font-bold text-white rounded-full bg-gradient-to-r from-blue-500 to-purple-500 ring-4 ring-white/20">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        @if ($index === 0)
                                            <div class="absolute flex items-center justify-center w-8 h-8 text-xs bg-yellow-500 rounded-full -bottom-2 -right-2">⭐</div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Player Info -->
                                <div class="text-center">
                                    <h3 class="mb-2 text-2xl font-bold text-white">{{ $user->name }}</h3>
                                    <div class="mb-4 text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-400">
                                        #{{ $user->pivot->rank }}
                                    </div>
                                    <div class="inline-flex items-center px-4 py-2 text-sm font-semibold text-blue-200 border rounded-full bg-gradient-to-r from-blue-500/20 to-purple-500/20 border-blue-500/30">
                                        {{ $index === 0 ? 'Champion' : ($index === 1 ? 'Elite' : 'Master') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    @if ($recentMatches->count() > 0)
    <!-- Recent Matches Section -->
    <div class="py-20 bg-gray-800/30">
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-16 text-center">
                <h2 class="mb-4 text-4xl font-bold text-white">Latest Battles</h2>
                <p class="text-xl text-gray-400">Recent matches and rank changes</p>
            </div>

            <div class="space-y-6">
                @foreach ($recentMatches as $match)
                    <div class="p-6 transition-all duration-300 border border-gray-700 bg-gray-800/50 backdrop-blur-sm rounded-xl hover:border-gray-600">
                        <!-- Match Header -->
                        <div class="flex flex-col items-start justify-between gap-4 mb-4 md:flex-row md:items-center">
                            <div class="flex items-center space-x-4">
                                <span class="text-lg font-semibold text-white">{{ optional($match->challenger)->name }}</span>
                                <div class="text-2xl">⚔️</div>
                                <span class="text-lg font-semibold text-white">{{ optional($match->opponent)->name }}</span>
                            </div>
                            <div class="text-sm text-gray-400">
                                {{ \Carbon\Carbon::parse($match->updated_at)->format('M d, Y') }} • Witnessed by {{ optional($match->witness)->name }}
                            </div>
                        </div>

                        <!-- Match Results -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            @if(isset($match->winner) && isset($match->loser))
                                <!-- Winner -->
                                <div class="p-4 border rounded-lg bg-green-500/10 border-green-500/30">
                                    <div class="flex items-center space-x-3">
                                        <div class="text-2xl">🏆</div>
                                        <div>
                                            <p class="font-semibold text-green-300">Winner: {{ $match->winner->name }}</p>
                                            @isset($match->winner_rank_change)
                                                <p class="text-sm text-green-400">
                                                    Rank: {{ $match->winner_rank_change['from'] }} → {{ $match->winner_rank_change['to'] }}
                                                    @if($match->winner_rank_change['from'] != $match->winner_rank_change['to'])
                                                        <span class="text-green-300">(↗️ Moved {{ $match->winner_rank_change['movement'] }})</span>
                                                    @else
                                                        <span class="text-gray-300">(No rank change)</span>
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Loser -->
                                <div class="p-4 border rounded-lg bg-red-500/10 border-red-500/30">
                                    <div class="flex items-center space-x-3">
                                        <div class="text-2xl">💥</div>
                                        <div>
                                            <p class="font-semibold text-red-300">{{ $match->loser->name }}</p>
                                            @isset($match->loser_rank_change)
                                                <p class="text-sm text-red-400">
                                                    Rank: {{ $match->loser_rank_change['from'] }} → {{ $match->loser_rank_change['to'] }}
                                                    @if($match->loser_rank_change['from'] != $match->loser_rank_change['to'])
                                                        <span class="text-red-300">(↘️ Moved {{ $match->loser_rank_change['movement'] }})</span>
                                                    @else
                                                        <span class="text-gray-300">(No rank change)</span>
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @else
                                <!-- No Clear Winner/Loser or Incomplete Data -->
                                <div class="col-span-full">
                                    <div class="p-4 border rounded-lg bg-gray-500/10 border-gray-500/30">
                                        <div class="flex items-center justify-center space-x-3">
                                            <div class="text-2xl">⚖️</div>
                                            <div class="text-center">
                                                <p class="font-semibold text-gray-300">Match Completed</p>
                                                <p class="text-sm text-gray-400">
                                                    {{ $match->challenger->name }} vs {{ $match->opponent->name }}
                                                </p>
                                                <p class="mt-1 text-xs text-gray-500">
                                                    No rank changes occurred or result data unavailable
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Features Section -->
    <div class="py-20">
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-16 text-center">
                <h2 class="mb-4 text-4xl font-bold text-white">Why Choose Pink Slip?</h2>
                <p class="text-xl text-gray-400">Built for competitive players who want fair, skill-based ranking</p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                <div class="p-8 transition-all duration-300 border border-gray-700 bg-gray-800/50 backdrop-blur-sm rounded-xl hover:border-blue-500/50 group">
                    <div class="flex items-center justify-center w-16 h-16 mb-6 transition-transform rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 group-hover:scale-110">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="mb-4 text-2xl font-bold text-white">Dynamic Challenges</h3>
                    <p class="leading-relaxed text-gray-400">Challenge players within your skill range. Our system ensures balanced matches that truly test your abilities.</p>
                </div>

                <div class="p-8 transition-all duration-300 border border-gray-700 bg-gray-800/50 backdrop-blur-sm rounded-xl hover:border-purple-500/50 group">
                    <div class="flex items-center justify-center w-16 h-16 mb-6 transition-transform rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 group-hover:scale-110">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="mb-4 text-2xl font-bold text-white">Real-Time Rankings</h3>
                    <p class="leading-relaxed text-gray-400">Watch your rank update instantly after each match. Track your progress and climb the competitive ladder.</p>
                </div>

                <div class="p-8 transition-all duration-300 border border-gray-700 bg-gray-800/50 backdrop-blur-sm rounded-xl hover:border-green-500/50 group">
                    <div class="flex items-center justify-center w-16 h-16 mb-6 transition-transform rounded-xl bg-gradient-to-r from-green-500 to-green-600 group-hover:scale-110">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="mb-4 text-2xl font-bold text-white">Trusted Witnesses</h3>
                    <p class="leading-relaxed text-gray-400">Every match is verified by neutral witnesses, ensuring fair play and preventing disputes or cheating.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action Section -->
    @guest
    <div class="py-20 bg-gradient-to-r from-blue-900/50 to-purple-900/50">
        <div class="max-w-4xl px-4 mx-auto text-center sm:px-6 lg:px-8">
            <h2 class="mb-6 text-4xl font-bold text-white">Ready to Prove Your Worth?</h2>
            <p class="mb-8 text-xl text-gray-300">Join the most competitive Valorant ranking community and start your climb to the top.</p>

            <div class="flex flex-col justify-center gap-4 sm:flex-row">
                <a href="{{ route('register') }}"
                    class="relative inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white transition-all duration-300 transform shadow-xl group bg-gradient-to-r from-pink-600 to-purple-600 rounded-xl hover:from-pink-700 hover:to-purple-700 hover:scale-105">
                    <span class="absolute inset-0 transition-opacity bg-gradient-to-r from-pink-600 to-purple-600 rounded-xl blur-lg opacity-30 group-hover:opacity-50"></span>
                    <span class="relative">Start Your Journey</span>
                </a>
                <a href="{{ route('login') }}"
                    class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-gray-200 transition-all duration-300 border-2 border-gray-600 bg-gray-800/50 backdrop-blur-sm rounded-xl hover:bg-gray-700/50 hover:border-gray-500">
                    Already Have an Account?
                </a>
            </div>
        </div>
    </div>
    @endguest
</div>
