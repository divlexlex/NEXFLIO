@extends('layouts.admin')

@section('title', 'Clients')

@section('content')
<h1 class="h3 mb-1">Clients</h1>
<p class="text-muted mb-4">Client profiles, preferences, and appointment history.</p>

<div class="card p-3">
    <form method="GET" action="{{ route('admin.clients') }}" class="row g-2 align-items-end mb-3">
        <div class="col-md-6">
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Search name, email, phone…" autocomplete="off">
        </div>
        <div class="col-md-2">
            <button class="btn btn-spa">Search</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr><th>Client</th><th>Email</th><th>Mobile</th><th>Bookings</th><th>Last visit</th><th>Client since</th></tr>
            </thead>
            <tbody>
                @forelse($clients as $client)
                    <tr>
                        <td>
                            <a href="{{ route('admin.clients.show', $client) }}" class="fw-semibold">
                                {{ $client->clientProfile?->fullName() ?: $client->name }}
                            </a>
                        </td>
                        <td>{{ $client->email }}</td>
                        <td>{{ $client->clientProfile?->mobile_number ?? '—' }}</td>
                        <td>{{ $client->bookings_count }}</td>
                        <td>{{ $client->last_appointment_date ? \Carbon\Carbon::parse($client->last_appointment_date)->format('M j, Y') : 'No visits yet' }}</td>
                        <td>{{ $client->created_at->format('M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            {{ $q !== '' ? 'No clients found.' : 'No clients yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $clients->links() }}
</div>
@endsection
