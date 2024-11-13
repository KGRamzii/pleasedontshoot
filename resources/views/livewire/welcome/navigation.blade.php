<nav class="flex justify-end flex-1 -mx-3">
    @auth
        <a href="{{ url('/dashboard') }}"
            class="rounded-md px-3 py-2 text-stone-50 ring-1 ring-transparent transition hover:text-stone-300/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-slate-400 dark:hover:text-white/80 dark:focus-visible:ring-white">
            Dashboard
        </a>
    @else
        <a href="{{ route('login') }}"
            class="rounded-md px-3 py-2 text-stone-50 ring-1 ring-transparent transition hover:text-stone-300/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-slate-400 dark:hover:text-white/80 dark:focus-visible:ring-slate-700">
            Log-In
        </a>

        @if (Route::has('register'))
            <a href="{{ route('register') }}"
                class="rounded-md px-3 py-2 text-stone-50 ring-1 ring-transparent transition hover:text-stone-300/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-slate-400 dark:hover:text-white/80 dark:focus-visible:ring-white">
                Register
            </a>
        @endif
    @endauth
</nav>
