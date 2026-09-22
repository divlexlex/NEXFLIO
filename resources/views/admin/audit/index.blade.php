@extends('layouts.admin')

@section('title', 'Activity Log')

@section('content')
<h1 class="h3 mb-1">Activity Log</h1>
<p class="text-muted small mb-3">
    Append-only record of every create, update, delete, and restore across the system.
    Entries can never be edited or removed.
</p>

<div class="card p-3 mb-4 bg-body-tertiary">
    <div class="row g-3 small">
        <div class="col-md-6">
            <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>What this is</div>
            <p class="text-muted mb-0">
                Every time a record is created, changed, deleted, or restored — an appointment,
                a payment, inventory, staff, services, promos, leave — the system automatically
                writes one line here: <em>who</em> did it, <em>when</em>, and the exact
                <em>before → after</em> values. It's written by the app itself, not typed by
                anyone, so it can't be forgotten or faked.
            </p>
        </div>
        <div class="col-md-6">
            <div class="fw-semibold mb-1"><i class="bi bi-shield-lock me-1"></i>Why it can't be tampered with</div>
            <p class="text-muted mb-0">
                Audit rows are immutable at the database-model level — any attempt to update or
                delete one throws an error. There is deliberately no edit or delete button
                anywhere on this page. Passwords and tokens are stripped before a change is
                logged, so secrets never land in the trail. Only the Owner can view it.
            </p>
        </div>
        <div class="col-12">
            <div class="fw-semibold mb-1"><i class="bi bi-list-columns-reverse me-1"></i>Reading a row</div>
            <p class="text-muted mb-0">
                <strong>Who</strong> is the signed-in user who made the change (<em>System</em>
                for automated jobs like appointment reminders). <strong>Event</strong> is
                created / updated / deleted / restored. <strong>Record</strong> names the model
                and its id (e.g. <code>Appointment #42</code>). <strong>Changes</strong> shows
                each field that moved, old value in red → new value in green; for creates and
                deletes, expand “View values” to see the full snapshot. Use the filters above
                to narrow by event type, model, date range, or user.
            </p>
        </div>
    </div>
</div>

<div class="card p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label small mb-0">Event</label>
            <select name="event" class="form-select form-select-sm">
                <option value="">Any</option>
                @foreach($events as $event)
                    <option value="{{ $event }}" @selected(request('event') === $event)>{{ ucfirst($event) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">Model</label>
            <input name="model" value="{{ request('model') }}" class="form-control form-control-sm" placeholder="e.g. Appointment">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-spa">Filter</button>
            <a href="{{ route('admin.audit-logs') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>When</th>
                    <th>Who</th>
                    <th>Event</th>
                    <th>Record</th>
                    <th>Changes (before → after)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-muted">{{ $log->id }}</td>
                        <td class="text-muted small">{{ $log->created_at->format('M j, Y g:i:s A') }}</td>
                        <td>{{ $log->user->name ?? 'System' }}</td>
                        <td>
                            <span class="badge {{ match($log->event) {
                                'created' => 'text-bg-success',
                                'updated' => 'text-bg-primary',
                                'deleted' => 'text-bg-danger',
                                default => 'text-bg-secondary',
                            } }}">{{ $log->event }}</span>
                        </td>
                        <td class="small">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td>
                        <td style="max-width: 420px;">
                            @if($log->event === 'updated' && is_array($log->new_values))
                                @foreach($log->new_values as $field => $newValue)
                                    <div class="small font-monospace">
                                        <span class="text-muted">{{ $field }}:</span>
                                        <span class="text-danger">{{ json_encode($log->old_values[$field] ?? null) }}</span>
                                        →
                                        <span class="text-success">{{ json_encode($newValue) }}</span>
                                    </div>
                                @endforeach
                            @else
                                <details>
                                    <summary class="small text-muted">View values</summary>
                                    <pre class="small bg-light p-2 rounded mb-0" style="white-space: pre-wrap;">{{
                                        json_encode($log->new_values ?? $log->old_values, JSON_PRETTY_PRINT)
                                    }}</pre>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No audit entries match the filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div>
@endsection
