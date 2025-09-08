<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Dashboard') }}
            </h2>
            <div class="flex items-center space-x-4">
                <!-- User Stats Card -->
                <div class="hidden sm:block p-3 bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg">
                    <div class="text-white text-sm">
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
    <div class="relative overflow-hidden bg-gradient-to-b from-gray-900 to-blue-900 mb-8">
        <div class="absolute inset-0 bg-black opacity-50"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/30 to-purple-500/30"></div>
        
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <div class="text-center md:text-left">
                    <h1 class="text-3xl sm:text-4xl font-bold text-white mb-4">
                        Welcome Back, <span class="text-blue-400">{{ Auth::user()->name }}</span>
                    </h1>
                    <p class="text-gray-300 text-lg mb-6">
                        Ready to climb the ranks? Challenge other players and prove your worth.
                    </p>
                    <a href="{{ route('challenges') }}" wire:navigate 
                       class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300">
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-xl rounded-lg overflow-hidden">
                <div class="p-6">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6">Current Rankings</h3>
                    <div class="overflow-x-auto">
                        <livewire:rankings />
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
