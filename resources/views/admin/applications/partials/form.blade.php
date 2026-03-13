@php
    $application = $application ?? null;
@endphp

<section class="admin-form-section">
    <div class="admin-form-section-head">
        <p class="section-kicker">Profil Aplikasi</p>
        <h4 class="section-title">Identitas dan endpoint utama</h4>
    </div>

    <div class="admin-form-grid">
        @if ($application === null)
            <div class="admin-field-card">
                <label for="code" class="form-label">Kode Aplikasi</label>
                <input id="code" name="code" class="form-input" value="{{ old('code') }}" placeholder="BLASKU">
                <p class="field-help">Dipakai aplikasi saat terhubung ke Payment Hub.</p>
                @error('code')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>
        @else
            <div class="admin-field-card">
                <label class="form-label">Kode Aplikasi</label>
                <input class="form-input" value="{{ $application->code }}" disabled>
                <input type="hidden" name="code" value="{{ $application->code }}">
                <p class="field-help">Kode dikunci agar koneksi aplikasi tetap stabil.</p>
            </div>
        @endif

        <div class="admin-field-card">
            <label for="name" class="form-label">Nama Aplikasi</label>
            <input id="name" name="name" class="form-input" value="{{ old('name', $application?->name) }}"
                placeholder="Blasku Website">
            <p class="field-help">Gunakan nama yang mudah dikenali operator.</p>
            @error('name')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="admin-field-card">
            <label for="default_provider" class="form-label">Default Provider</label>
            <select id="default_provider" name="default_provider" class="form-select">
                <option value="">Pilih provider</option>
                @foreach ($providers as $code => $name)
                    <option value="{{ $code }}" @selected(old('default_provider', $application?->default_provider) === $code)>{{ $name }}</option>
                @endforeach
            </select>
            <p class="field-help">Hanya provider aktif yang tersedia di daftar ini.</p>
            @error('default_provider')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="admin-field-card">
            <label for="webhook_url" class="form-label">Webhook URL</label>
            <input id="webhook_url" name="webhook_url" class="form-input"
                value="{{ old('webhook_url', $application?->webhook_url) }}"
                placeholder="https://client-app.test/api/webhook/payment">
            <p class="field-help">Status pembayaran akan dikirim ke alamat ini.</p>
            @error('webhook_url')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>

<section class="admin-form-section">
    <div class="admin-form-section-head">
        <p class="section-kicker">Akses Operasional</p>
        <h4 class="section-title">Status layanan</h4>
    </div>

    <div class="admin-toggle-grid">
        <label class="admin-toggle-card">
            <input type="hidden" name="status" value="0">
            <input class="checkbox-input" type="checkbox" name="status" value="1"
                @checked(old('status', $application?->status ?? true))>
            <span>
                <span class="checkbox-title">Aplikasi aktif</span>
                <span class="checkbox-copy">Jika dimatikan, aplikasi tidak bisa mengakses Client API.</span>
            </span>
        </label>
    </div>
</section>

<div class="form-actions">
    <button type="submit" class="button-primary">{{ $submitLabel }}</button>
    <a href="{{ route('admin.applications') }}" class="button-link">Kembali ke daftar aplikasi</a>
</div>
