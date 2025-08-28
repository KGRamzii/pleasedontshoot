<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $teams;
    public $pendingInvitations = 0;

    public function mount()
    {
        // Fetch teams that the current user belongs to
        $this->teams = auth()->user()->teams;
        
        // Get pending team invitations count
        $this->pendingInvitations = DB::table('team_user')
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->count();
    }

    public function getListeners()
    {
        return [
            "echo-private:App.Models.User." . auth()->id() . ".Notification" => 'refreshInvitations',
        ];
    }

    public function refreshInvitations()
    {
        $this->pendingInvitations = DB::table('team_user')
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
};
?>

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 dark:bg-gray-800 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Left Side -->
            <div class="flex items-center">
                <!-- Logo -->
                <div class="flex items-center shrink-0">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block w-auto text-gray-800 fill-current h-14 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('challenges')" :active="request()->routeIs('challenges')" wire:navigate>
                        {{ __('Pink Slip') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Mobile menu button -->
            <div class="flex items-center sm:hidden">
                <button @click="open = !open" class="inline-flex items-center justify-center p-2 text-gray-500 transition duration-150 ease-in-out rounded-md dark:text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700 focus:text-gray-500 dark:focus:text-gray-400">
                    <svg class="w-6 h-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': !open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>


            <!-- Right Side -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 space-x-4">
                <!-- Team Invitations Notification -->
                @if($pendingInvitations > 0)
                    <a href="{{ route('teams.invitations') }}" class="relative inline-flex items-center p-2">
                        <svg class="w-6 h-6 text-gray-500 transition duration-150 ease-in-out dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full">
                            {{ $pendingInvitations }}
                        </span>
                    </a>
                @endif

                <!-- Teams Dropdown -->
                <x-dropdown align="left" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out bg-white border border-transparent rounded-md dark:text-gray-400 dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                            {{ __('Teams') }}
                            <div class="ms-1">
                                <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <!-- Create New Team Button -->
                        <button wire:click="$dispatch('open-modal')"
                            class="block w-full text-left px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                            {{ __('Create New Team') }}
                        </button>

                        <!-- Existing Teams -->
                        @foreach ($teams as $team)
                            <x-dropdown-link :href="route('teams.show', $team->id)" wire:navigate>
                                {{ $team->name }}
                            </x-dropdown-link>
                        @endforeach
                    </x-slot>
                </x-dropdown>


                <!-- Settings Dropdown -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out bg-white border border-gray-200 rounded-md dark:text-gray-400 dark:bg-gray-800 dark:border-gray-700 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:border-gray-300 dark:focus:border-gray-600 active:bg-gray-50 dark:active:bg-gray-700">
                            <div x-data="{ name: '{{ auth()->user()->name }}' }" x-text="name"
                                x-on:profile-updated.window="name = $event.detail.name"
                                class="max-w-[100px] sm:max-w-[150px] truncate"></div>
                            <div class="ms-1">
                                <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>

        <!-- Responsive Navigation Menu -->
        <div :class="{ 'block': open, 'hidden': !open }" class="fixed inset-0 z-40 sm:hidden">
            <!-- Dark overlay -->
            <div class="fixed inset-0 bg-gray-600 bg-opacity-75" @click="open = false"></div>

            <!-- Menu panel -->
            <div class="fixed inset-y-0 right-0 max-w-xs w-full bg-white dark:bg-gray-800 shadow-xl flex flex-col">
                <!-- Close button -->
                <div class="absolute top-0 right-0 -mr-12 pt-2">
                    <button @click="open = false" class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                        <span class="sr-only">Close sidebar</span>
                        <svg class="h-6 w-6 text-white" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Menu content -->
                <div class="flex-1 h-0 overflow-y-auto">
                    <div class="p-4">
                        <!-- User info -->
                        <div class="flex items-center mb-6 px-2">
                            <div class="flex-shrink-0">
                                <div class="h-12 w-12 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center">
                                    <span class="text-xl font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <div class="text-base font-medium text-gray-800 dark:text-gray-200">{{ auth()->user()->name }}</div>
                                <div class="text-sm font-medium text-gray-500">{{ auth()->user()->email }}</div>
                            </div>
                        </div>

                        <!-- Navigation Links -->
                        <div class="space-y-1">
                            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate
                                class="block px-3 py-2 rounded-md text-base font-medium transition-colors duration-200">
                                {{ __('Dashboard') }}
                            </x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('challenges')" :active="request()->routeIs('challenges')" wire:navigate
                                class="block px-3 py-2 rounded-md text-base font-medium transition-colors duration-200">
                                {{ __('Pink Slip') }}
                            </x-responsive-nav-link>
                        </div>

                        <!-- Teams Section -->
                        <div class="mt-8">
                            <h3 class="px-3 text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ __('Teams') }}
                            </h3>
                            <div class="mt-2 space-y-1">
                                <button wire:click="$dispatch('open-modal')"
                                    class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors duration-200 group">
                                    <svg class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    {{ __('Create New Team') }}
                                </button>
                                @foreach ($teams as $team)
                                    <a href="{{ route('teams.show', $team->id) }}" wire:navigate
                                        class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors duration-200">
                                        <span class="truncate">{{ $team->name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <!-- Footer Links -->
                        <div class="mt-8 border-t border-gray-200 dark:border-gray-600 pt-4 space-y-1">
                            <x-responsive-nav-link :href="route('profile')" wire:navigate
                                class="block px-3 py-2 rounded-md text-base font-medium transition-colors duration-200">
                                {{ __('Profile') }}
                            </x-responsive-nav-link>
                            <button wire:click="logout" class="w-full text-start">
                                <x-responsive-nav-link
                                    class="block px-3 py-2 rounded-md text-base font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200">
                                    {{ __('Log Out') }}
                                </x-responsive-nav-link>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
