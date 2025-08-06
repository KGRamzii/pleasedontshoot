<?php

use Livewire\Volt\Component;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $name = '';
    public $errorMessage = '';

    // Validation rules
    protected $rules = [
        'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
    ];

    // Reset error message when name changes
    public function updatedName()
    {
        $this->errorMessage = '';
    }

    // Method to handle team creation
    public function createTeam()
    {
        $this->validate();

        try {
            $team = DB::transaction(function () {
                // Create the new team
                $team = Team::create([
                    'name' => $this->name,
                    'user_id' => Auth::id(),
                    'personal_team' => false,
                ]);

                // Add the team creator to the team_user table
                DB::table('team_user')->insert([
                    'team_id' => $team->id,
                    'user_id' => Auth::id(),
                    'status' => 'approved',
                    'rank' => 1,
                    'role' => 'admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $team;
            });

            // Clear any error message
            $this->errorMessage = '';

            // Reset form
            $this->reset('name');

            // Flash success message
            session()->flash('success', 'Team created successfully.');

            // Redirect to the team page
            return redirect()->route('teams.show', $team);
        } catch (\Exception $e) {
            $this->errorMessage = 'Something went wrong. Please try again.';
            // Clear any success message
            session()->forget('success');
        }
    }
}; ?>

<div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 relative">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white text-center">Create New Team</h2>

        @if (session()->has('success') && !$errorMessage)
            <div class="mt-4 p-3 text-sm text-green-700 bg-green-100 rounded-md dark:text-green-100 dark:bg-green-900">
                {{ session('success') }}
            </div>
        @endif

        @if ($errorMessage)
            <div class="mt-4 p-3 text-sm text-red-700 bg-red-100 rounded-md dark:text-red-100 dark:bg-red-900">
                {{ $errorMessage }}
            </div>
        @endif

        <form wire:submit.prevent="createTeam" class="mt-6">
            <div class="mb-6">
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Team Name
                </label>
                <input wire:model="name" id="name" type="text"
                    class="block w-full px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    placeholder="Enter team name" required>
                @error('name')
                    <div class="text-sm text-red-500 mt-2">{{ $message }}</div>
                @enderror
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
</div>
