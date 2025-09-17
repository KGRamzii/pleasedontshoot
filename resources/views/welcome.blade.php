<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Valorant Rankings</title>
    <meta name="description" content="Valorant Rankings - Track your competitive performance and team stats.">

    <link rel="icon" href="{{ asset('Logo/pds(white).svg') }}" type="image/svg+xml">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="antialiased bg-gray-900">
    <!-- Navigation -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-gray-900/95 backdrop-blur-sm">
        <div class="px-4 py-3 sm:px-6">
            @if (Route::has('login'))
                <livewire:welcome.navigation />
            @endif
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-16"> <!-- Added padding-top to account for fixed header -->
        <!-- Hero Section Component -->
        <livewire:welcome.index />
    </main>

    <!-- Simplified Footer -->
    <footer class="bg-gray-900/95 backdrop-blur-sm">
        <div class="px-6 py-12 mx-auto max-w-7xl">
            <div class="md:flex md:items-center md:justify-between">

                <div class="mt-8 md:order-1 md:mt-0">
                    <p class="text-base text-center text-gray-400">

                    </p>
                </div>

            </div>

        </div>
        <livewire:layout.footer />
    </footer>
</body>

</html>
