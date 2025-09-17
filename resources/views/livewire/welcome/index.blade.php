<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Team;
use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;
use App\Models\RankHistory;

new class extends Component {
    public $topRankedUsers;
    public $recentMatches;
    public $totalPlayers = 0;
    public $totalMatches = 0;
    public $activeTeams = 0;

    public function mount()
    {
        $this->loadTopRankedUsers();
        $this->loadRecentMatches();
        $this->loadStats();
    }

    public function loadTopRankedUsers()
    {
        $team = Auth::check() ? Auth::user()->teams()->first() : null;

        if ($team) {
            \Log::info('Loading top ranked users - code updated');

            $this->topRankedUsers = $team->users()
                ->withPivot('rank')
                ->orderByPivot('rank', 'asc')
                ->take(3)
                ->get();
        } else {
            // For non-authenticated users, show top players from all teams (example)
            $this->topRankedUsers = collect();
            \Log::info('No team found - returning empty collection');
        }
    }

    public function loadRecentMatches()
    {
        $this->recentMatches = Challenge::where('status', 'completed')
            ->with(['challenger', 'opponent', 'witness', 'rankHistories'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($match) {
                $rankHistories = $match->rankHistories->groupBy('user_id');

                foreach ($rankHistories as $userId => $histories) {
                    $history = $histories->first();

                    if ($history->previous_rank !== $history->new_rank) {
                        if ($history->previous_rank > $history->new_rank) {
                            $match->winner = User::find($userId);
                            $match->winner_rank_change = [
                                'from' => $history->previous_rank,
                                'to'   => $history->new_rank,
                                'movement' => 'up',
                            ];
                        } else {
                            $match->loser = User::find($userId);
                            $match->loser_rank_change = [
                                'from' => $history->previous_rank,
                                'to'   => $history->new_rank,
                                'movement' => 'down',
                            ];
                        }
                    } else {
                        if ($history->previous_rank == $history->new_rank) {
                            if (!isset($match->winner)) {
                                $match->winner = User::find($userId);
                            } else {
                                $match->loser = User::find($userId);
                            }
                        }
                    }
                }

                return $match;
            });
    }

    public function loadStats()
    {
        $this->totalPlayers = User::count();
        $this->totalMatches = Challenge::where('status', 'completed')->count();
        $this->activeTeams = Team::count();
    }
};?>

<div class="min-h-screen bg-gray-900">
    <!-- Hero Section -->
    <div class="relative overflow-hidden bg-gradient-to-br from-gray-900 via-blue-900 to-purple-900">
        <!-- Animated Background Pattern -->
        <div class="absolute inset-0 opacity-20">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-purple-500/10"></div>
            <div class="absolute top-0 left-0 w-full h-full">
                <div class="absolute top-1/4 left-1/4 w-2 h-2 bg-blue-400 rounded-full animate-pulse"></div>
                <div class="absolute top-1/3 right-1/3 w-1 h-1 bg-purple-400 rounded-full animate-ping"></div>
                <div class="absolute bottom-1/4 right-1/4 w-3 h-3 bg-pink-400 rounded-full animate-pulse"></div>
                <div class="absolute bottom-1/3 left-1/3 w-1 h-1 bg-blue-300 rounded-full animate-ping"></div>
            </div>
        </div>

        <!-- Hero Background Image -->
        <div class="absolute inset-0 bg-black/40"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 via-transparent to-transparent"></div>
        
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-32">
            <div class="text-center">
                <!-- Main Title -->
                <div class="mb-8">
                    <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-blue-500 tracking-tight leading-tight">
                        VALORANT
                    </h1>
                    <div class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-white mt-2">
                        Pink Slip <span class="text-pink-500">Challenge</span>
                    </div>
                    <div class="text-2xl sm:text-3xl md:text-4xl font-semibold text-blue-400 mt-2">
                        Climb • Compete • Conquer
                    </div>
                </div>

                <!-- Subtitle -->
                <p class="mt-6 max-w-3xl mx-auto text-lg sm:text-xl md:text-2xl text-gray-300 leading-relaxed">
                    The ultimate competitive ranking system for Valorant players. Challenge opponents, climb the ladder, 
                    and prove you're the best in your community.
                </p>

                <!-- CTA Buttons -->
                <div class="mt-10 flex flex-col sm:flex-row justify-center gap-4 sm:gap-6">
                    @auth
                        <a href="{{ route('dashboard') }}"
                            class="group relative inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl hover:from-blue-700 hover:to-purple-700 transition-all duration-300 transform hover:scale-105 shadow-xl hover:shadow-2xl">
                            <span class="absolute inset-0 bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl blur-lg opacity-30 group-hover:opacity-50 transition-opacity"></span>
                            <span class="relative">Enter Dashboard</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="group relative inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-pink-600 to-purple-600 rounded-xl hover:from-pink-700 hover:to-purple-700 transition-all duration-300 transform hover:scale-105 shadow-xl hover:shadow-2xl">
                            <span class="absolute inset-0 bg-gradient-to-r from-pink-600 to-purple-600 rounded-xl blur-lg opacity-30 group-hover:opacity-50 transition-opacity"></span>
                            <span class="relative">Sign In</span>
                        </a>
                        <a href="{{ route('register') }}"
                            class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-gray-200 bg-gray-800/50 backdrop-blur-sm border-2 border-gray-600 rounded-xl hover:bg-gray-700/50 hover:border-gray-500 transition-all duration-300 transform hover:scale-105">
                            Join the Fight
                        </a>
                    @endauth
                </div>

                <!-- Stats Bar -->
                <div class="mt-16 grid grid-cols-1 sm:grid-cols-3 gap-6 max-w-2xl mx-auto">
                    <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg p-6 border border-gray-700">
                        <div class="text-3xl font-bold text-blue-400">{{ $totalPlayers }}+</div>
                        <div class="text-gray-300 mt-1">Active Players</div>
                    </div>
                    <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg p-6 border border-gray-700">
                        <div class="text-3xl font-bold text-purple-400">{{ $totalMatches }}+</div>
                        <div class="text-gray-300 mt-1">Matches Played</div>
                    </div>
                    <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg p-6 border border-gray-700">
                        <div class="text-3xl font-bold text-pink-400">{{ $activeTeams }}+</div>
                        <div class="text-gray-300 mt-1">Teams</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- How It Works Section -->
    <div class="py-20 bg-gray-800/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">How It Works</h2>
                <p class="text-xl text-gray-400">Simple, fair, and competitive</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center group">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-500 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                        <span class="text-3xl font-bold text-white">1</span>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Challenge Players</h3>
                    <p class="text-gray-400 leading-relaxed">Find opponents within your skill range and send them a challenge. Every match matters in the ranking system.</p>
                </div>

                <div class="text-center group">
                    <div class="bg-gradient-to-r from-purple-500 to-pink-500 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                        <span class="text-3xl font-bold text-white">2</span>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Fair Competition</h3>
                    <p class="text-gray-400 leading-relaxed">Every match is witnessed by a neutral third party to ensure fair play and accurate results reporting.</p>
                </div>

                <div class="text-center group">
                    <div class="bg-gradient-to-r from-pink-500 to-blue-500 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                        <span class="text-3xl font-bold text-white">3</span>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Climb the Ranks</h3>
                    <p class="text-gray-400 leading-relaxed">Win matches to climb the ladder. Your ranking reflects your true skill level in the community.</p>
                </div>
            </div>
        </div>
    </div>

    @if ($topRankedUsers->count() > 0)
    <!-- Top Players Section -->
    <div class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">Hall of Champions</h2>
                <p class="text-xl text-gray-400">Meet our current top-ranked legends</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
                @foreach ($topRankedUsers as $index => $user)
                    <div class="relative group">
                        @if ($index === 0)
                            <!-- First Place - Special Styling -->
                            <div class="relative bg-gradient-to-br from-yellow-400/20 to-yellow-600/20 rounded-2xl p-8 transform transition-all duration-500 group-hover:scale-105 group-hover:rotate-1 border-2 border-yellow-500/30">
                                <div class="absolute -top-4 -right-4 w-12 h-12 bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center text-2xl animate-pulse">
                                    👑
                                </div>
                        @elseif ($index === 1)
                            <!-- Second Place -->
                            <div class="relative bg-gradient-to-br from-gray-300/20 to-gray-500/20 rounded-2xl p-8 transform transition-all duration-500 group-hover:scale-105 group-hover:-rotate-1 border-2 border-gray-400/30">
                                <div class="absolute -top-4 -right-4 w-12 h-12 bg-gradient-to-r from-gray-300 to-gray-500 rounded-full flex items-center justify-center text-2xl">
                                    🥈
                                </div>
                        @else
                            <!-- Third Place -->
                            <div class="relative bg-gradient-to-br from-amber-600/20 to-amber-800/20 rounded-2xl p-8 transform transition-all duration-500 group-hover:scale-105 group-hover:rotate-1 border-2 border-amber-600/30">
                                <div class="absolute -top-4 -right-4 w-12 h-12 bg-gradient-to-r from-amber-600 to-amber-800 rounded-full flex items-center justify-center text-2xl">
                                    🥉
                                </div>
                        @endif

                                <!-- Player Avatar -->
                                <div class="flex justify-center mb-6">
                                    <div class="relative">
                                        <div class="w-24 h-24 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-3xl font-bold text-white ring-4 ring-white/20">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        @if ($index === 0)
                                            <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-xs">⭐</div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Player Info -->
                                <div class="text-center">
                                    <h3 class="text-2xl font-bold text-white mb-2">{{ $user->name }}</h3>
                                    <div class="text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-400 mb-4">
                                        #{{ $user->pivot->rank }}
                                    </div>
                                    <div class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-gradient-to-r from-blue-500/20 to-purple-500/20 text-blue-200 border border-blue-500/30">
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">Latest Battles</h2>
                <p class="text-xl text-gray-400">Recent matches and rank changes</p>
            </div>

            <div class="space-y-6">
                @foreach ($recentMatches as $match)
                    <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-6 border border-gray-700 hover:border-gray-600 transition-all duration-300">
                        <!-- Match Header -->
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                            <div class="flex items-center space-x-4">
                                <span class="text-lg font-semibold text-white">{{ optional($match->challenger)->name }}</span>
                                <div class="text-2xl">⚔️</div>
                                <span class="text-lg font-semibold text-white">{{ optional($match->opponent)->name }}</span>
                            </div>
                            <div class="text-sm text-gray-400">
                                {{ $match->updated_at->format('M d, Y') }} • Witnessed by {{ optional($match->witness)->name }}
                            </div>
                        </div>

                        <!-- Match Results -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @isset($match->winner)
                                <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="text-2xl">🏆</div>
                                        <div>
                                            <p class="text-green-300 font-semibold">Winner: {{ $match->winner->name }}</p>
                                            @isset($match->winner_rank_change)
                                                <p class="text-sm text-green-400">
                                                    Rank: {{ $match->winner_rank_change['from'] }} → {{ $match->winner_rank_change['to'] }}
                                                    <span class="text-green-300">(↗️ Moved {{ $match->winner_rank_change['movement'] }})</span>
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endisset

                            @isset($match->loser)
                                <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="text-2xl">💥</div>
                                        <div>
                                            <p class="text-red-300 font-semibold">{{ $match->loser->name }}</p>
                                            @isset($match->loser_rank_change)
                                                <p class="text-sm text-red-400">
                                                    Rank: {{ $match->loser_rank_change['from'] }} → {{ $match->loser_rank_change['to'] }}
                                                    <span class="text-red-300">(↘️ Moved {{ $match->loser_rank_change['movement'] }})</span>
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endisset
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Features Section -->
    <div class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-4">Why Choose Pink Slip?</h2>
                <p class="text-xl text-gray-400">Built for competitive players who want fair, skill-based ranking</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700 hover:border-blue-500/50 transition-all duration-300 group">
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Dynamic Challenges</h3>
                    <p class="text-gray-400 leading-relaxed">Challenge players within your skill range. Our system ensures balanced matches that truly test your abilities.</p>
                </div>

                <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700 hover:border-purple-500/50 transition-all duration-300 group">
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Real-Time Rankings</h3>
                    <p class="text-gray-400 leading-relaxed">Watch your rank update instantly after each match. Track your progress and climb the competitive ladder.</p>
                </div>

                <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700 hover:border-green-500/50 transition-all duration-300 group">
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Trusted Witnesses</h3>
                    <p class="text-gray-400 leading-relaxed">Every match is verified by neutral witnesses, ensuring fair play and preventing disputes or cheating.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action Section -->
    @guest
    <div class="py-20 bg-gradient-to-r from-blue-900/50 to-purple-900/50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-bold text-white mb-6">Ready to Prove Your Worth?</h2>
            <p class="text-xl text-gray-300 mb-8">Join the most competitive Valorant ranking community and start your climb to the top.</p>
            
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="{{ route('register') }}"
                    class="group relative inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-pink-600 to-purple-600 rounded-xl hover:from-pink-700 hover:to-purple-700 transition-all duration-300 transform hover:scale-105 shadow-xl">
                    <span class="absolute inset-0 bg-gradient-to-r from-pink-600 to-purple-600 rounded-xl blur-lg opacity-30 group-hover:opacity-50 transition-opacity"></span>
                    <span class="relative">Start Your Journey</span>
                </a>
                <a href="{{ route('login') }}"
                    class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-gray-200 bg-gray-800/50 backdrop-blur-sm border-2 border-gray-600 rounded-xl hover:bg-gray-700/50 hover:border-gray-500 transition-all duration-300">
                    Already Have an Account?
                </a>
            </div>
        </div>
    </div>
    @endguest

    <!-- Footer -->
    <footer class="bg-gray-900/80 backdrop-blur-sm border-t border-gray-800">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="text-2xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-pink-500 to-purple-500 mb-4">
                    Valorant Pink Slip
                </div>
                <p class="text-gray-400">&copy; {{ date('Y') }} Pink Slip Challenge. Built for competitive excellence.</p>
            </div>
        </div>
    </footer>
</div>