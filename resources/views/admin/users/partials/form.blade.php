@php
    $managedUser = $managedUser ?? null;
@endphp

<section class="admin-form-section">
    <div class="admin-form-section-head">
        <p class="section-kicker">Profil Admin</p>
        <h4 class="section-title">Data identitas dan akses login</h4>
    </div>

    <div class="admin-form-grid">
        <div class="admin-field-card">
            <label for="username" class="form-label">Username</label>
            <input id="username" name="username" class="form-input" value="{{ old('username', $managedUser?->username) }}"
                placeholder="superadmin">
            <p class="field-help">Dipakai saat login ke admin panel.</p>
            @error('username')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="admin-field-card">
            <label for="name" class="form-label">Nama</label>
            <input id="name" name="name" class="form-input" value="{{ old('name', $managedUser?->name) }}"
                placeholder="Super Admin">
            <p class="field-help">Nama ini tampil di panel admin.</p>
            @error('name')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="admin-field-card">
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email" class="form-input"
                value="{{ old('email', $managedUser?->email) }}" placeholder="admin@example.com">
            <p class="field-help">Opsional untuk identifikasi akun.</p>
            @error('email')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="admin-field-card">
            <label for="password" class="form-label">Password</label>
            <input id="password" name="password" type="password" class="form-input" value=""
                placeholder="{{ $managedUser ? 'Kosongkan jika tidak ingin mengganti' : 'Minimal 8 karakter' }}">
            <p class="field-help">
                {{ $managedUser ? 'Kosongkan jika tidak ingin mengganti password.' : 'Gunakan minimal 8 karakter.' }}
            </p>
            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>

<section class="admin-form-section">
    <div class="admin-form-section-head">
        <p class="section-kicker">Kontrol Akses</p>
        <h4 class="section-title">Status akun</h4>
    </div>

    <div class="admin-toggle-grid">
        <label class="admin-toggle-card">
            <input type="hidden" name="is_active" value="0">
            <input class="checkbox-input" type="checkbox" name="is_active" value="1"
                @checked(old('is_active', $managedUser?->is_active ?? true))>
            <span>
                <span class="checkbox-title">User aktif</span>
                <span class="checkbox-copy">Jika dimatikan, user tidak dapat login ke admin panel.</span>
            </span>
        </label>
    </div>
</section>

<div class="form-actions">
    <button type="submit" class="button-primary">{{ $submitLabel }}</button>
    <a href="{{ route('admin.users.index') }}" class="button-link">Kembali ke daftar user</a>
</div>
