@extends('layouts.admin')

@section('title', 'Master Schedule')

@push('head')
<style>
    /* Master Schedule hover detail cards — pure CSS reveal, no JavaScript.
       The block is the positioning anchor; the card floats beside it. */
    .nx-ms-block { position: relative; }
    .nx-ms-hovercard {
        position: absolute; left: calc(100% + 8px); top: 0; width: 270px; z-index: 1040;
        background: var(--nex-sidebar-bg); color: var(--nex-sidebar-text);
        border: 1px solid rgba(201, 162, 75, .55); border-radius: .5rem;
        box-shadow: 0 12px 28px rgba(0, 0, 0, .35);
        padding: .75rem; font-size: .8rem; text-align: left; white-space: normal;
        opacity: 0; visibility: hidden; transform: translateY(4px); pointer-events: none;
        transition: opacity .16s ease, transform .16s ease, visibility 0s linear .16s;
    }
    .nx-ms-block:hover .nx-ms-hovercard {
        opacity: 1; visibility: visible; transform: translateY(0); pointer-events: none;
        transition-delay: 120ms;
    }
    /* Edge columns (Friday/Saturday) open to the left instead. */
    .nx-ms-hovercard-flip { left: auto; right: calc(100% + 8px); }
    .nx-ms-hovercard-title { font-weight: 700; margin-bottom: .35rem; }
    .nx-ms-hovercard-row {
        display: flex; justify-content: space-between; gap: .75rem;
        padding: .2rem 0;
    }
    .nx-ms-hovercard-row > span:first-child { color: var(--nex-sidebar-muted); }
    .nx-ms-hovercard hr { border-color: rgba(201, 162, 75, .35); opacity: 1; margin: .45rem 0; }
    /* The .table-responsive scroll container would clip the floating card,
       so the schedule grid opts out of it at desktop widths. Below 992px
       the card stays hidden (hover is a desktop interaction). */
    @media (min-width: 992px) {
        .nx-ms-scroll.table-responsive { overflow: visible; }
    }
    @media (max-width: 991.98px) {
        .nx-ms-hovercard { display: none; }
    }
</style>
@endpush

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
    <div class="table-responsive nx-ms-scroll">
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
                                <span class="badge text-bg-primary mt-1">{{ \Carbon\Carbon::parse($row['shift']->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($row['shift']->end_time)->format('g:i A') }}</span>
                            @else
                                <span class="text-muted small mt-1 d-inline-block">Not assigned</span>
                            @endif
                        </td>
                        @foreach($weekDays as $day)
                            <td class="text-center" style="min-width:120px;">
                                <div class="d-block p-1">
                                    @if($row['days'][$day]['on_leave'])
                                        <span class="badge text-bg-secondary mb-1">On leave</span>
                                    @endif
                                    @forelse($row['days'][$day]['appointments'] as $appointment)
                                        @php($flipCard = \Carbon\Carbon::parse($day)->dayOfWeek >= 5)
                                        <span class="nx-ms-block d-block border rounded px-1 py-1 mb-1 small bg-light">
                                            <strong>{{ \Carbon\Carbon::parse($appointment->start_time)->format('g:i A') }} · {{ $appointment->displayServiceName() }}</strong>
                                            <span class="d-block text-muted">{{ $appointment->clientName() }}</span>
                                            <div class="nx-ms-hovercard{{ $flipCard ? ' nx-ms-hovercard-flip' : '' }}">
                                                <div class="nx-ms-hovercard-title">{{ $appointment->displayServiceName() }}</div>
                                                <div class="nx-ms-hovercard-row">
                                                    <span>Appointment ID</span>
                                                    <span>{{ $appointment->referenceCode() }}</span>
                                                </div>
                                                <div class="nx-ms-hovercard-row">
                                                    <span>Client</span>
                                                    <span>{{ $appointment->clientName() }}</span>
                                                </div>
                                                <div class="nx-ms-hovercard-row">
                                                    <span>Date &amp; time</span>
                                                    <span>{{ $appointment->appointment_date->format('M j, Y') }} · {{ \Carbon\Carbon::parse($appointment->start_time)->format('g:i A') }}</span>
                                                </div>
                                                <div class="nx-ms-hovercard-row">
                                                    <span>Duration</span>
                                                    <span>{{ \Carbon\Carbon::parse($appointment->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($appointment->start_time)->addMinutes($appointment->service?->duration_minutes ?? 0)->format('g:i A') }}</span>
                                                </div>
                                                @php($cursor = \Carbon\Carbon::parse($appointment->start_time)->addMinutes($appointment->service?->duration_minutes ?? 0))
                                                @foreach($appointment->addedServices as $added)
                                                    @php($addedStart = !empty($added->start_time) ? \Carbon\Carbon::parse($added->start_time) : $cursor)
                                                    @php($addedEnd = $addedStart->copy()->addMinutes($added->service?->duration_minutes ?? 0))
                                                    <div class="nx-ms-hovercard-row">
                                                        <span>{{ $added->displayName() }}</span>
                                                        <span>{{ $addedStart->format('g:i A') }} – {{ $addedEnd->format('g:i A') }}</span>
                                                    </div>
                                                    @php($cursor = $addedEnd)
                                                @endforeach
                                                <div class="nx-ms-hovercard-row">
                                                    <span>Personnel</span>
                                                    <span>{{ $row['staff']->fullName() }}</span>
                                                </div>
                                                <div class="nx-ms-hovercard-row">
                                                    <span>Status</span>
                                                    <span class="badge {{ match($appointment->status->value) {
                                                        'completed' => 'text-bg-success',
                                                        'cancelled' => 'text-bg-danger',
                                                        'no-show' => 'text-bg-secondary',
                                                        'in-service' => 'text-bg-primary',
                                                        default => 'text-bg-warning',
                                                    } }}">{{ $appointment->status->label() }}</span>
                                                </div>
                                                <hr>
                                                <div class="nx-ms-hovercard-row">
                                                    <span>Total</span>
                                                    <span>₱{{ number_format((float) $appointment->booked_price, 2) }}</span>
                                                </div>
                                                <div class="nx-ms-hovercard-row">
                                                    <span>Paid</span>
                                                    <span>₱{{ number_format($appointment->verifiedPaid(), 2) }}</span>
                                                </div>
                                                <div class="nx-ms-hovercard-row">
                                                    <span>Balance</span>
                                                    <span>₱{{ number_format($appointment->balanceDue(), 2) }}</span>
                                                </div>
                                            </div>
                                        </span>
                                    @empty
                                        @if(!$row['days'][$day]['on_leave'])
                                            <span class="text-muted">—</span>
                                        @endif
                                    @endforelse
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
