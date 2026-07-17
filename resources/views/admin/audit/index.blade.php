@extends('layouts.admin')

@section('title', 'Audit Trail')

@section('content')
<h1 class="h3 mb-1">Forensic Audit Trail</h1>
<p class="text-muted small mb-4">
    Append-only record of every create, update, delete, and restore across the system.
    Entries can never be edited or removed.
</p>

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
