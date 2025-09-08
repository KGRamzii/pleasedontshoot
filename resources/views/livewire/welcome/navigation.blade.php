<div>
    <nav class="flex justify-end flex-1 space-x-2 px-2 sm:px-4">
        @auth
            <a href="{{ url('/dashboard') }}"
            class="rounded-md px-4 py-2 text-sm sm:text-base text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200 shadow-sm">
            Dashboard
        </a>
    @else
        <a href="{{ route('login') }}"
            class="rounded-md px-4 py-2 text-sm sm:text-base text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200 shadow-sm">
            Log In
        </a>

        @if (Route::has('register'))
            <a href="{{ route('register') }}"
                class="rounded-md px-4 py-2 text-sm sm:text-base text-gray-200 bg-gray-800 hover:bg-gray-700 transition-colors duration-200 shadow-sm">
                Register
            </a>
            @endif
        @endauth
    </nav>
</div>
