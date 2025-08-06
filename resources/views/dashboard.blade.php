<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6 lg:py-12">
        <div class="max-w-full px-2 mx-auto sm:max-w-7xl sm:px-3 lg:px-8">
            <div class="overflow-x-auto bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-0 text-gray-900 sm:p-6 lg:p-6 dark:text-gray-100">
                    <livewire:rankings />
                </div>
            </div>
        </div>
    </div>


</x-app-layout>
