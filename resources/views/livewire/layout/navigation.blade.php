<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;
use App\Models\Team;
use App\Models\User;

new class extends Component {
    public $teams;

    public function mount()
    {
        // Fetch teams that the current user belongs to
        $this->teams = auth()->user()->teams;
        // dd($this->teams);
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
            <div class="flex">
                <!-- Logo -->
                <div class="flex items-center shrink-0">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block w-auto text-gray-800 fill-current h-14 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>
                </div>

                <!-- Challenges Link -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('challenges')" :active="request()->routeIs('challenges')" wire:navigate>
                        {{ __('Pink Slip') }}
                    </x-nav-link>
                </div>


            </div>


            <!-- Right Side -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
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
                            class="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out bg-white border border-transparent rounded-md dark:text-gray-400 dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                            <div x-data="{ name: '{{ auth()->user()->name }}' }" x-text="name"
                                x-on:profile-updated.window="name = $event.detail.name"></div>
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
        <div :class="{ 'block': open, 'hidden': !open }" class="hidden sm:hidden">
            <div class="pt-2 pb-3 space-y-1">
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('challenges')" :active="request()->routeIs('challenges')" wire:navigate>
                    {{ __('Pink Slip') }}
                </x-responsive-nav-link>
            </div>

            <!-- Responsive Teams Dropdown -->
            <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
                <div class="px-4">
                    <div class="text-base font-medium text-gray-800 dark:text-gray-200">{{ auth()->user()->name }}
                    </div>
                    <div class="text-sm font-medium text-gray-500">{{ auth()->user()->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile')" wire:navigate>
                        {{ __('Profile') }}
                    </x-responsive-nav-link>
                    <button wire:click="logout" class="w-full text-start">
                        <x-responsive-nav-link>
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </button>
                </div>
            </div>
        </div>
    </div>
</nav>
