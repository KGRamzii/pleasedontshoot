<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'PDS') }}</title>

<link rel="icon" href="{{ asset('Logo/pds(white).svg') }}" type="image/svg+xml">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <livewire:layout.navigation />

        <!-- Other layout content -->

        <!-- Modal Overlay -->
        <div x-data="{ show: false }" @open-modal.window="show = true" @close-modal.window="show = false" x-show="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="relative p-6 bg-white rounded-lg shadow-lg dark:bg-gray-800">
                <div class="absolute top-2 right-2">
                    <!-- Close Button -->
                    <button @click="show = false" class="text-gray-600 hover:text-gray-900 dark:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Livewire Create Team Component -->
                <livewire:team-create-overlay />
            </div>
        </div>

        <!-- Scripts -->
        @livewireScripts
        @stack('scripts')
</body>


<!-- Page Heading -->
@if (isset($header))
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
            {{ $header }}
        </div>
    </header>
@endif

<!-- Page Content -->
<main>
    {{ $slot }}
</main>
<livewire:layout.footer />
</div>
</body>

</html>
