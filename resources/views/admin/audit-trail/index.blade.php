@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
    <section class="panel-card">
        <form method="GET" class="filter-grid">
            <div class="form-field form-field-wide">
                <label for="q" class="form-label">Search</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input" placeholder="event type">
            </div>
            <div class="form-field">
                <label for="application" class="form-label">Application</label>
                <select id="application" name="application" class="form-select">
                    <option value="">All applications</option>
                    @foreach ($applications as $code => $name)
                        <option value="{{ $code }}" @selected(($filters['application'] ?? null) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="provider" class="form-label">Provider</label>
                <select id="provider" name="provider" class="form-select">
                    <option value="">All providers</option>
                    @foreach ($providers as $code => $name)
                        <option value="{{ $code }}" @selected(($filters['provider'] ?? null) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label for="payment" class="form-label">Payment ID</label>
                <input id="payment" name="payment" value="{{ $filters['payment'] ?? '' }}" class="form-input" placeholder="pay_...">
            </div>
            <div class="form-field">
                <label for="event_type" class="form-label">Event type</label>
                <input id="event_type" name="event_type" value="{{ $filters['event_type'] ?? '' }}" class="form-input" placeholder="payment.created, webhook.dispatched">
            </div>
            <div class="form-actions">
                <button type="submit" class="button-primary">Apply filters</button>
                <a href="{{ route('admin.audit-trail') }}" class="button-link">Reset</a>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <div class="stack-list">
            @forelse ($events as $event)
                <x-event-item :event="$event" />
            @empty
                <div class="empty-state">Belum ada audit trail event.</div>
            @endforelse
        </div>

        <div class="pagination-wrap">
            {{ $events->links() }}
        </div>
    </section>
@endsection
