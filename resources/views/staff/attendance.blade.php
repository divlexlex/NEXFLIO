@extends('layouts.staff')

@section('title', 'My Attendance')

@section('content')
@php($profile = auth()->user()->staffProfile)

<div class="mb-4">
    <h1 class="h3 mb-1">My Attendance</h1>
    <p class="text-muted mb-0">Your own attendance history by month.</p>
</div>

<div class="row g-3 mb-3">
    {{-- ATTENDANCE — same card as the dashboard, so clock in/out & break
         work from this page too. --}}
    <div class="col-md-6">
        <div class="card p-3 h-100">
            <h2 class="h6 text-uppercase text-muted mb-3">Today's Attendance</h2>
            @if(!$todayAttendance)
                <p class="mb-3">You haven't timed in yet today.</p>
                <form method="POST" action="{{ route('staff.attendance.time-in') }}">
                    @csrf
                    <button class="btn btn-spa"><i class="bi bi-box-arrow-in-right me-1"></i>Time In</button>
                </form>
            @elseif(!$todayAttendance->time_out)
                <p class="mb-2">
                    Timed in at <strong>{{ $todayAttendance->time_in->format('g:i A') }}</strong>.
                    @if($todayAttendance->isLate())
                        <span class="badge text-bg-warning">Late {{ $todayAttendance->lateMinutes() }}m</span>
                    @endif
                </p>
                <p class="mb-3">
                    @if($profile?->is_on_break)
                        <span class="badge text-bg-warning">On break since {{ $profile->break_started_at?->format('g:i A') }}</span>
                    @else
                        <span class="badge text-bg-success">Active</span>
                    @endif
                </p>
                <div class="d-flex gap-2 mb-3">
                    <form method="POST" action="{{ route('staff.attendance.break') }}">
                        @csrf
                        <button class="btn btn-outline-secondary">
                            @if($profile?->is_on_break)
                                <i class="bi bi-play-fill me-1"></i>End Break
                            @else
                                <i class="bi bi-pause-fill me-1"></i>Start Break
                            @endif
                        </button>
                    </form>
                    <form method="POST" action="{{ route('staff.attendance.time-out') }}">
                        @csrf
                        <button class="btn btn-outline-secondary"><i class="bi bi-box-arrow-right me-1"></i>Time Out</button>
                    </form>
                </div>
                <p class="text-muted small mb-0">Total so far: {{ $todayAttendance->workedForHumans() }}</p>
            @else
                <p class="mb-2">
                    Timed in at <strong>{{ $todayAttendance->time_in->format('g:i A') }}</strong>@if($todayAttendance->isLate()) <span class="badge text-bg-warning">Late {{ $todayAttendance->lateMinutes() }}m</span>@endif,
                    timed out at <strong>{{ $todayAttendance->time_out->format('g:i A') }}</strong>.
                </p>
                <p class="text-muted small mb-0">Shift complete — see you next time. Total today: {{ $todayAttendance->workedForHumans() }}.</p>
            @endif
        </div>
    </div>

    {{-- MONTHLY SUMMARY --}}
    <div class="col-md-6">
        <div class="card p-3 h-100">
            <h2 class="h6 text-uppercase text-muted mb-3">Monthly Summary · {{ $monthStart->format('F Y') }}</h2>
            <div class="row g-3">
                <div class="col-4">
                    <div class="text-muted small">Days present</div>
                    <div class="fs-4 fw-semibold" data-summary="days-present">{{ $daysPresent }}</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Total hours</div>
                    <div class="fs-4 fw-semibold" data-summary="total-hours">{{ $totalHours }}</div>
                </div>
                <div class="col-4">
                    <div class="text-muted small">Lates</div>
                    <div class="fs-4 fw-semibold" data-summary="lates">{{ $lateCount }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- HISTORY --}}
<div class="card p-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h6 text-uppercase text-muted mb-0">{{ $monthStart->format('F Y') }}</h2>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('staff.attendance.index', ['month' => $prevMonth]) }}">
                <i class="bi bi-chevron-left"></i> Prev
            </a>
            @unless($isCurrentMonth)
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('staff.attendance.index', ['month' => $nextMonth]) }}">
                    Next <i class="bi bi-chevron-right"></i>
                </a>
            @endunless
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($days as $day)
                    @php($row = $rows->get($day->toDateString()))
                    <tr>
                        <td class="text-nowrap">{{ $day->format('D, M j') }}</td>
                        @if($row)
                            <td class="text-nowrap">
                                {{ $row->time_in->format('g:i A') }}
                                @if($row->isLate())
                                    <span class="badge text-bg-warning">Late {{ $row->lateMinutes() }}m</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if($row->time_out)
                                    {{ $row->time_out->format('g:i A') }}
                                @else
                                    <span class="text-muted">still in</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                {{ $row->workedForHumans() }}@if(!$row->time_out) <span class="text-muted small">so far</span>@endif
                            </td>
                            <td>
                                @if($row->isLate())
                                    <span class="badge text-bg-warning">Late</span>
                                @else
                                    <span class="badge text-bg-success">On time</span>
                                @endif
                            </td>
                        @else
                            <td colspan="4" class="text-muted">No record</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
