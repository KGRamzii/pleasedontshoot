<!-- resources/views/teams/show.blade.php -->
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-pretty dark:text-slate-300">
            {{ $team->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <livewire:team-members :team="$team" />
        </div>
    </div>

</x-app-layout>
