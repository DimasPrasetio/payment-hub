<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="topbar-burger" id="sidebarToggle" aria-label="Open menu">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
        </button>
        <div>
            <p class="topbar-kicker">{{ $pageKicker }}</p>
            <h2 class="topbar-title">{{ $pageHeading }}</h2>
            @if (! empty($pageDescription))
                <p class="topbar-copy">{{ $pageDescription }}</p>
            @endif
        </div>
    </div>

    <div class="topbar-actions">
        <span class="topbar-badge">{{ strtoupper(app()->environment()) }}</span>
        @auth
            <span class="topbar-user">{{ auth()->user()->username }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="button-link">Logout</button>
            </form>
        @endauth
    </div>
</header>
