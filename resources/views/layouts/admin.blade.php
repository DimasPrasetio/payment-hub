<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $pageTitle ?? 'Payment Hub Admin')</title>
    <meta name="description" content="{{ $pageDescription ?? 'Admin dashboard untuk monitoring payment hub.' }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=space-grotesk:500,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="admin-shell">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        @include('partials.sidebar')

        <div class="admin-main">
            @include('partials.topbar')

            <main class="content-area">
                @if (session('success'))
                    <section class="notice notice-success">
                        <p class="notice-title">Perubahan disimpan</p>
                        <p class="notice-copy">{{ session('success') }}</p>
                    </section>
                @endif

                @if (session('issued_credentials'))
                    <section class="notice notice-info">
                        <p class="notice-title">Credential baru tersedia</p>
                        <p class="notice-copy">Nilai berikut hanya ditampilkan pada halaman ini. Gunakan untuk menghubungkan client app ke Payment Orchestrator.</p>
                        <div class="credential-list">
                            @foreach (session('issued_credentials') as $label => $value)
                                <div class="credential-item">
                                    <p class="credential-label">{{ strtoupper(str_replace('_', ' ', $label)) }}</p>
                                    <p class="credential-value">{{ $value }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if (session('error'))
                    <section class="notice notice-error">
                        <p class="notice-title">Aksi tidak dapat dilakukan</p>
                        <p class="notice-copy">{{ session('error') }}</p>
                    </section>
                @endif

                @if ($errors->any())
                    <section class="notice notice-error">
                        <p class="notice-title">Validasi gagal</p>
                        <div class="notice-copy">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </section>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
