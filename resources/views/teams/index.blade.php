{{-- resources/views/teams/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Teams') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold">Your Teams</h3>
                        <a href="{{ route('teams.create') }}"
                            class="px-4 py-2 font-bold text-white bg-blue-500 rounded hover:bg-blue-700">
                            Create New Team
                        </a>
                    </div>

                    <div class="space-y-6">
                        @foreach ($teams as $team)
                            <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50">
                                <div>
                                    <h4 class="font-semibold">{{ $team->name }}</h4>
                                    @if ($team->personal_team)
                                        <span class="text-sm text-gray-500">Personal Team</span>
                                    @endif
                                </div>
                                <a href="{{ route('teams.show', $team) }}" class="text-blue-600 hover:text-blue-800">
                                    View Team
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
