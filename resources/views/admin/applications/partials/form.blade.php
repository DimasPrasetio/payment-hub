@php
    $application = $application ?? null;
@endphp

<div class="form-grid-2">
    @if ($application === null)
        <div class="form-field">
            <label for="code" class="form-label">Kode Aplikasi</label>
            <input id="code" name="code" class="form-input" value="{{ old('code') }}" placeholder="BLASKU">
            <p class="field-help">Kode ini akan dipakai client app pada request `application_code`.</p>
            @error('code')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>
    @else
        <div class="form-field">
            <label class="form-label">Kode Aplikasi</label>
            <input class="form-input" value="{{ $application->code }}" disabled>
            <input type="hidden" name="code" value="{{ $application->code }}">
            <p class="field-help">Kode aplikasi saat ini dipertahankan agar integrasi client app tetap stabil.</p>
        </div>
    @endif

    <div class="form-field">
        <label for="name" class="form-label">Nama Aplikasi</label>
        <input id="name" name="name" class="form-input" value="{{ old('name', $application?->name) }}"
            placeholder="Blasku Website">
        @error('name')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-field">
        <label for="default_provider" class="form-label">Default Provider</label>
        <select id="default_provider" name="default_provider" class="form-select">
            <option value="">Pilih provider</option>
            @foreach ($providers as $code => $name)
                <option value="{{ $code }}" @selected(old('default_provider', $application?->default_provider) === $code)>{{ $name }}</option>
            @endforeach
        </select>
        <p class="field-help">Hanya provider aktif yang bisa ditautkan ke aplikasi baru.</p>
        @error('default_provider')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-field">
        <label for="webhook_url" class="form-label">Webhook URL</label>
        <input id="webhook_url" name="webhook_url" class="form-input"
            value="{{ old('webhook_url', $application?->webhook_url) }}"
            placeholder="https://client-app.test/api/webhook/payment">
        <p class="field-help">Payment Orchestrator akan mengirim status payment ke URL ini.</p>
        @error('webhook_url')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<label class="checkbox-field">
    <input type="hidden" name="status" value="0">
    <input class="checkbox-input" type="checkbox" name="status" value="1"
        @checked(old('status', $application?->status ?? true))>
    <span>
        <span class="checkbox-title">Aplikasi aktif</span>
        <span class="checkbox-copy">Jika dimatikan, client app tidak bisa lagi mengakses client API.</span>
    </span>
</label>

<div class="form-actions">
    <button type="submit" class="button-primary">{{ $submitLabel }}</button>
    <a href="{{ route('admin.applications') }}" class="button-link">Kembali ke daftar aplikasi</a>
</div>
