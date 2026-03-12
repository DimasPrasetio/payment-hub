<aside class="admin-sidebar" id="sidebar">
    <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
        <div class="brand-mark">PH</div>
        <div>
            <p class="brand-eyebrow">Payment Hub</p>
            <h1 class="brand-title">Admin Console</h1>
        </div>
    </a>

    @foreach ($adminNavigation as $group)
        <div class="sidebar-group">
            <p class="sidebar-label">{{ $group['label'] }}</p>
            <nav class="sidebar-nav">
                @foreach ($group['items'] as $item)
                    <a href="{{ route($item['route']) }}"
                        class="sidebar-link {{ request()->routeIs(...$item['active']) ? 'is-active' : '' }}">
                        <svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                        </svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    @endforeach

    <div class="sidebar-footer">
        <div class="sidebar-meta">
            <span class="signal-dot bg-emerald-400"></span>
            <div>
                <p class="sidebar-meta-title">{{ strtoupper(app()->environment()) }}</p>
                <p class="sidebar-meta-copy">{{ config('app.timezone') }}</p>
            </div>
        </div>

        <div class="sidebar-note">
            Dashboard ini menampilkan data pembayaran secara real-time. Anda bisa melihat riwayat transaksi dan status
            sistem di sini.
        </div>

        <a href="{{ route('api.health') }}" class="button-link button-link-light">Cek Status Sistem</a>
    </div>
</aside>