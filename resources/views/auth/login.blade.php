<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-shell">
        <section class="auth-panel">
            <p class="auth-kicker">Payment Orchestrator</p>
            <h1 class="auth-title">Admin Panel Login</h1>
            <p class="auth-copy">Masuk menggunakan username admin untuk mengelola aplikasi client, provider, dan operasional pembayaran.</p>

            @if ($errors->any())
                <section class="notice notice-error mt-6">
                    <p class="notice-title">Login gagal</p>
                    <div class="notice-copy">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                </section>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                @csrf
                <div class="form-field">
                    <label for="username" class="form-label">Username</label>
                    <input id="username" name="username" class="form-input" value="{{ old('username') }}" autofocus
                        placeholder="superadmin">
                </div>

                <div class="form-field">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" name="password" type="password" class="form-input" placeholder="••••••••">
                </div>

                <label class="checkbox-field">
                    <input class="checkbox-input" type="checkbox" name="remember" value="1">
                    <span>
                        <span class="checkbox-title">Remember session</span>
                        <span class="checkbox-copy">Simpan sesi login di browser ini.</span>
                    </span>
                </label>

                <button type="submit" class="button-primary w-full">Masuk ke Admin Panel</button>
            </form>

            <div class="auth-help">
                Seeder default akan membuat akun awal dengan username <strong>superadmin</strong> dan password <strong>superadmin</strong>.
            </div>
        </section>
    </main>
</body>
</html>
