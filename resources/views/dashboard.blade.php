<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Dashboard') }}
            </h2>
            <div class="flex items-center space-x-4">
                <!-- User Stats Card -->
                <div class="hidden p-3 rounded-lg shadow-lg sm:block bg-gradient-to-r from-blue-600 to-blue-800">
                    <div class="text-sm text-white">

                        @php
                            $userTeam = Auth::user()->teams()->select('team_user.rank')->first();
                        @endphp
                        <span class="font-semibold">Your Rank:</span>
                        <span class="ml-1">#{{ $userTeam?->pivot->rank ?? 'N/A' }}</span>

                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <!-- Hero Section with Background -->
    <div class="relative mb-8 overflow-hidden bg-gradient-to-b from-gray-900 to-blue-900">
        <div class="absolute inset-0 bg-black opacity-50"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/30 to-purple-500/30"></div>

        <div class="relative px-4 py-12 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="grid items-center grid-cols-1 gap-8 md:grid-cols-2">
                <div class="text-center md:text-left">
                    <h1 class="mb-4 text-3xl font-bold text-white sm:text-4xl">
                        Welcome Back, <span class="text-blue-400">{{ Auth::user()->name }}</span>
                    </h1>
                    <p class="mb-6 text-lg text-gray-300">
                        Ready to climb the ranks? Challenge other players and prove your worth.
                    </p>
                    <a href="{{ route('challenges') }}" wire:navigate
                       class="inline-block px-6 py-3 font-bold text-white transition duration-300 bg-blue-600 rounded-lg hover:bg-blue-700">
                        Issue a Challenge
                    </a>
                </div>
                <div class="hidden md:block">
                    <img src="{{ asset('valArt/Valorant_EP-8-Teaser_The-arrival.jpg') }}" alt="Valorant Art" class="rounded-lg shadow-2xl">
                </div>
            </div>
        </div>
    </div>

    <!-- Rankings Section -->
    <div class="py-4 sm:py-6 lg:py-8">
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white rounded-lg shadow-xl dark:bg-gray-800">
                <div class="p-6">
                    <h3 class="mb-6 text-xl font-semibold text-gray-900 dark:text-gray-100">Current Rankings</h3>
                    <div class="overflow-x-auto">
                        <livewire:rankings />
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="mb-6 text-xl font-semibold text-gray-900 dark:text-gray-100">My Rank History</h3>
                    <div class="overflow-x-auto">
                        <livewire:rankhistory />
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
