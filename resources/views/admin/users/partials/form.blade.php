@php
    $managedUser = $managedUser ?? null;
@endphp

<div class="form-grid-2">
    <div class="form-field">
        <label for="username" class="form-label">Username</label>
        <input id="username" name="username" class="form-input" value="{{ old('username', $managedUser?->username) }}"
            placeholder="superadmin">
        @error('username')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-field">
        <label for="name" class="form-label">Nama</label>
        <input id="name" name="name" class="form-input" value="{{ old('name', $managedUser?->name) }}"
            placeholder="Super Admin">
        @error('name')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-field">
        <label for="email" class="form-label">Email</label>
        <input id="email" name="email" type="email" class="form-input"
            value="{{ old('email', $managedUser?->email) }}" placeholder="admin@example.com">
        @error('email')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-field">
        <label for="password" class="form-label">Password</label>
        <input id="password" name="password" type="password" class="form-input" value=""
            placeholder="{{ $managedUser ? 'Kosongkan jika tidak ingin mengganti' : 'Minimal 8 karakter' }}">
        @if ($managedUser)
            <p class="field-help">Kosongkan jika password tidak ingin diubah.</p>
        @endif
        @error('password')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<label class="checkbox-field">
    <input type="hidden" name="is_active" value="0">
    <input class="checkbox-input" type="checkbox" name="is_active" value="1"
        @checked(old('is_active', $managedUser?->is_active ?? true))>
    <span>
        <span class="checkbox-title">User aktif</span>
        <span class="checkbox-copy">Jika dimatikan, user tidak dapat login ke admin panel.</span>
    </span>
</label>

<div class="form-actions">
    <button type="submit" class="button-primary">{{ $submitLabel }}</button>
    <a href="{{ route('admin.users.index') }}" class="button-link">Kembali ke daftar user</a>
</div>
