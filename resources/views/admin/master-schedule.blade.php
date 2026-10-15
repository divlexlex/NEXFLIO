@extends('layouts.admin')

@section('title', 'Master Schedule')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0">Master Schedule</h1>
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.master-schedule', ['week' => $prevWeek]) }}">‹ Prev week</a>
        <strong>{{ $weekLabel }}</strong>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.master-schedule', ['week' => $nextWeek]) }}">Next week ›</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.master-schedule') }}">This week</a>
    </div>
</div>

<div class="card p-3 mb-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="min-width:200px;">Staff</th>
                    @foreach($weekDays as $day)
                        <th class="text-center">
                            {{ \Carbon\Carbon::parse($day)->format('D') }}
                            <span class="d-block small fw-normal">{{ \Carbon\Carbon::parse($day)->format('M j') }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['staff']->fullName() }}</strong>
                            <div class="small text-muted">{{ $row['staff']->staffProfile->position ?? 'Staff' }}</div>
                            @if($row['shift'])
                                <span class="badge text-bg-primary mt-1">{{ $row['shift']->shift === 'am' ? 'AM' : 'PM' }} · {{ \Carbon\Carbon::parse($row['shift']->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($row['shift']->end_time)->format('g:i A') }}</span>
                            @else
                                <span class="text-muted small mt-1 d-inline-block">Not assigned</span>
                            @endif
                        </td>
                        @foreach($weekDays as $day)
                            @php($isSelected = $selected && $selected['staff']->id === $row['staff']->id && $selected['date'] === $day)
                            <td class="text-center {{ $isSelected ? 'table-warning' : '' }}" style="min-width:120px;">
                                <a href="{{ route('admin.master-schedule', ['week' => $weekStart, 'staff' => $row['staff']->id, 'date' => $day]) }}"
                                   class="d-block text-decoration-none text-reset p-1">
                                    @if($row['days'][$day]['on_leave'])
                                        <span class="badge text-bg-secondary mb-1">On leave</span>
                                    @endif
                                    @forelse($row['days'][$day]['appointments'] as $appointment)
                                        <span class="d-block border rounded px-1 py-1 mb-1 small bg-light">
                                            <strong>{{ \Carbon\Carbon::parse($appointment->start_time)->format('g:i A') }} · {{ $appointment->displayServiceName() }}</strong>
                                            <span class="d-block text-muted">{{ $appointment->clientName() }}</span>
                                        </span>
                                    @empty
                                        @if(!$row['days'][$day]['on_leave'])
                                            <span class="text-muted">—</span>
                                        @endif
                                    @endforelse
                                </a>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3">
    @if($selected)
        <h2 class="h5 mb-1">{{ $selected['staff']->fullName() }} — {{ \Carbon\Carbon::parse($selected['date'])->format('l, M j') }}</h2>
        <p class="text-muted small mb-3">
            @if($selected['shift'])
                {{ $selected['shift']->shift === 'am' ? 'AM' : 'PM' }} · {{ \Carbon\Carbon::parse($selected['shift']->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($selected['shift']->end_time)->format('g:i A') }}
            @else
                Not assigned
            @endif
            @if($selected['on_leave'])
                · <span class="badge text-bg-secondary">On leave</span>
            @endif
        </p>
        @if($selected['appointments']->isEmpty())
            <p class="text-muted mb-0">No appointments that day.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>Time</th><th>Service</th><th>Client</th><th>Appointment ID</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($selected['appointments'] as $appointment)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($appointment->start_time)->format('g:i A') }}</td>
                                <td>{{ $appointment->displayServiceName() }}</td>
                                <td>{{ $appointment->clientName() }}</td>
                                <td>{{ $appointment->referenceCode() }}</td>
                                <td>{{ $appointment->status->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        <p class="text-muted mb-0">Select a cell to see that day's appointments.</p>
    @endif
</div>
@endsection
