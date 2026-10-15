@extends('layouts.admin')

@section('title', 'Client Profile')

@section('content')
@php
    $displayName = $profile?->fullName() ?: $client->name;
    $initials = strtoupper(implode('', array_map(
        fn ($p) => mb_substr($p, 0, 1),
        array_slice(preg_split('/\s+/', trim($displayName)), 0, 2)
    ))) ?: '?';
@endphp

<a href="{{ route('admin.clients') }}" class="btn btn-sm btn-outline-secondary mb-3">‹ Back to Clients</a>

<div class="card p-3 mb-3">
    <div class="d-flex align-items-center gap-3">
        <span class="nx-avatar d-inline-flex align-items-center justify-content-center rounded-circle fw-bold"
              style="width:48px; height:48px; background:var(--nex-primary-bg); color:var(--nex-primary-text);">{{ $initials }}</span>
        <div>
            <h1 class="h4 mb-0">{{ $displayName }}</h1>
            <div class="text-muted small">{{ $client->email }} · {{ $profile?->mobile_number ?? '—' }}</div>
            <div class="text-muted small">
                @if($profile?->gender)
                    {{ $profile->gender->label() }}
                @endif
                @if($profile?->birthdate)
                    {{ $profile?->gender ? ' · ' : '' }}{{ $profile->birthdate->format('M j, Y') }}
                @endif
            </div>
            <div class="small mt-1">Client since {{ $client->created_at->format('M Y') }}</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card p-3 text-center h-100">
            <div class="display-6">{{ $totalBookings }}</div>
            <div class="text-muted small">Bookings</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 text-center h-100">
            <div class="display-6">{{ $completedCount }}</div>
            <div class="text-muted small">Completed</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 text-center h-100">
            <div class="display-6">{{ $cancelledCount }}</div>
            <div class="text-muted small">Cancelled / No-show</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 text-center h-100">
            <div class="display-6">₱{{ number_format($totalPaid, 2) }}</div>
            <div class="text-muted small">Total paid</div>
        </div>
    </div>
</div>

<div class="card p-3 mb-3">
    <h2 class="h6 mb-2">Preferences &amp; notes</h2>
    <form method="POST" action="{{ route('admin.clients.notes', $client) }}">
        @csrf
        @method('PATCH')
        <textarea name="preferences_notes" class="form-control mb-2" rows="3" maxlength="2000"
                  placeholder="Notes about this client — preferred personnel, allergies, preferences…">{{ old('preferences_notes', $profile?->preferences_notes) }}</textarea>
        @error('preferences_notes')
            <div class="text-danger small mb-2">{{ $message }}</div>
        @enderror
        <button class="btn btn-spa btn-sm">Save</button>
    </form>
</div>

<div class="card p-3">
    <h2 class="h6 mb-3">Appointment history</h2>
    @if($history->isEmpty())
        <p class="text-muted mb-0">No appointments yet.</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>Date</th><th>Time</th><th>Service</th><th>Personnel</th><th>Status</th><th>Appointment ID</th><th>Price</th></tr>
                </thead>
                <tbody>
                    @foreach($history as $appointment)
                        <tr>
                            <td>{{ $appointment->appointment_date->format('M j, Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($appointment->start_time)->format('g:i A') }}</td>
                            <td>{{ $appointment->displayServiceName() }}</td>
                            <td>{{ $appointment->personnel?->name ?? '—' }}</td>
                            <td>{{ $appointment->status->label() }}</td>
                            <td>{{ $appointment->referenceCode() }}
                                @if($appointment->status === \App\Enums\AppointmentStatus::Completed)
                                    <a href="{{ route('admin.appointments.receipt', $appointment->id) }}" class="d-block small">View receipt</a>
                                @endif
                            </td>
                            <td>{{ $appointment->price !== null ? '₱'.number_format($appointment->price, 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $history->links() }}
    @endif
</div>
@endsection
