<?php

use Livewire\Volt\Component;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $name = '';
    public $discord_team_id = '';

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
            'discord_team_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function createTeam()
    {
        $this->validate();

        try {
            $team = Team::create([
                'name' => $this->name,
                'discord_team_id' => $this->discord_team_id,
                'user_id' => Auth::id(),
                'personal_team' => 'false',
            ]);

            // Attach creator as admin
            $team->users()->attach(Auth::id(), [
                'status' => 'approved',
                'rank' => 1,
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            session()->flash('success', 'Team created successfully.');
            $this->reset(['name', 'discord_team_id']);

            return redirect()->route('teams.show', $team);
        } catch (\Exception $e) {
            session()->flash('error', 'Something went wrong. Please try again.');
        }
    }
};
?>

<x-modal name="create-team" :show="true">
    <div class="p-6">
        <h2 class="text-2xl font-semibold text-center text-gray-900 dark:text-white">Create New Team</h2>

        @if (session('success'))
            <div class="mt-4 p-3 text-sm text-green-700 bg-green-100 rounded-md dark:text-green-100 dark:bg-green-900">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mt-4 p-3 text-sm text-red-700 bg-red-100 rounded-md dark:text-red-100 dark:bg-red-900">
                {{ session('error') }}
            </div>
        @endif

        <form wire:submit.prevent="createTeam" class="mt-6 space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Team Name
                </label>
                <input wire:model="name" id="name" type="text"
                    class="block w-full px-4 py-2 mt-1 border rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300"
                    placeholder="Enter team name" required>
                @error('name') <div class="text-sm text-red-500 mt-2">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="discord_team_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Discord Team ID (optional)
                </label>
                <input wire:model="discord_team_id" id="discord_team_id" type="text"
                    class="block w-full px-4 py-2 mt-1 border rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300"
                    placeholder="Enter Discord Server/Channel ID">
                @error('discord_team_id') <div class="text-sm text-red-500 mt-2">{{ $message }}</div> @enderror
            </div>

            <div class="flex items-center justify-end space-x-4">
                <button type="button" wire:click="$dispatch('close-modal')"
                    class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-500">
                    Create Team
                </button>
            </div>
        </form>
    </div>
</x-modal>
