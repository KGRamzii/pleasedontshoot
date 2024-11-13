<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Challenges') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

            <div class="overflow-hidden mb-3 bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6  text-gray-900 dark:text-gray-100">
                    <livewire:challenges.witness />



                </div>

            </div>
            <div class="overflow-hidden mb-3 bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6  text-gray-900 dark:text-gray-100">
                    <livewire:challenges.challenge-list />



                </div>

            </div>
            <div class="overflow-hidden mb-3 bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">


                    <livewire:challenges.createchallenge />


                </div>

            </div>
        </div>

    </div>
</x-app-layout>
