<?php

use Livewire\Volt\Component;
use App\Models\User;
use function Livewire\Volt\{state};

new class extends Component {
    public $topRankedUsers;

    public function mount()
    {
        $this->loadTopRankedUsers();
    }

    public function loadTopRankedUsers()
    {
        $this->topRankedUsers = User::orderBy('rank')->take(3)->get();
    }
}; ?>

<div class="bg-gradient-to-b from-gray-900 via-gray-800 to-blue-900">
    <!-- Hero Section -->
    <div class="relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 bg-black opacity-50"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/30 to-purple-500/30"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold text-white tracking-tight">
                    Valorant Rankings
                    <span class="block text-blue-400">Challenge & Climb</span>
                </h1>
                <p class="mt-6 max-w-2xl mx-auto text-xl text-gray-300">
                    Challenge other players, prove your worth, and climb the ranks in our competitive Valorant
                    community.
                </p>
                <div class="mt-10 flex justify-center space-x-4">
                    @auth
                        <a href="{{ route('dashboard') }}"
                            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Get Started
                        </a>
                        <a href="{{ route('register') }}"
                            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-gray-200 bg-gray-800 hover:bg-gray-700">
                            Sign Up
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </div>

    <!-- Top Players Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-white">Current Top Players</h2>
            <p class="mt-4 text-gray-400">Meet our highest-ranked champions</p>
        </div>

        <div class="mt-12 grid grid-cols-1 gap-8 sm:grid-cols-3">
            @foreach ($topRankedUsers as $index => $user)
                <div class="relative group">
                    <div
                        class="relative bg-gray-800 rounded-lg overflow-hidden transform transition-all duration-300 group-hover:scale-105 group-hover:shadow-2xl">
                        <!-- Rank Position Indicator -->
                        <div class="absolute top-4 right-4 z-10">
                            @if ($index === 0)
                                <span
                                    class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-yellow-100 text-white text-xl font-bold">
                                    👑
                                </span>
                            @elseif($index === 1)
                                <span
                                    class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-400 text-white text-xl font-bold">
                                    🥈
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-amber-600 text-white text-xl font-bold">
                                    🥉
                                </span>
                            @endif
                        </div>

                        <!-- Player Card Content -->
                        <div class="px-6 py-8">
                            <div class="flex justify-center">
                                <div
                                    class="w-24 h-24 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-3xl font-bold text-white">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                            </div>
                            <div class="mt-4 text-center">
                                <h3 class="text-xl font-semibold text-white">{{ $user->name }}</h3>
                                <p class="mt-2 text-gray-400">Rank #{{ $user->rank }}</p>
                                <div class="mt-4">
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-900 text-blue-200">
                                        Top {{ $index + 1 }} Player
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Features Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
            <div class="bg-gray-800 rounded-lg p-8 transform transition-all duration-300 hover:scale-105">
                <div class="w-12 h-12 rounded-lg bg-blue-500 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Challenge System</h3>
                <p class="text-gray-400">Challenge other players and climb the ranks through direct competition.</p>
            </div>

            <div class="bg-gray-800 rounded-lg p-8 transform transition-all duration-300 hover:scale-105">
                <div class="w-12 h-12 rounded-lg bg-purple-500 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Live Rankings</h3>
                <p class="text-gray-400">Track your progress and see where you stand in real-time rankings.</p>
            </div>

            <div class="bg-gray-800 rounded-lg p-8 transform transition-all duration-300 hover:scale-105">
                <div class="w-12 h-12 rounded-lg bg-green-500 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Fair Witnesses</h3>
                <p class="text-gray-400">Every match is overseen by a witness to ensure fair play and accurate results.
                </p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="text-center text-gray-400">
                <p>&copy; {{ date('Y') }} Valorant Rankings. All rights reserved.</p>
            </div>
        </div>
    </footer>
</div>
