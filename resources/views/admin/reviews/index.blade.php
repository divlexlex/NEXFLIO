@extends('layouts.admin')

@section('title', 'Feedback')

@section('content')
<h1 class="h3 mb-1">Feedback</h1>
<p class="text-muted mb-4">Client feedback — from completed appointments only.</p>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="card p-3 text-center h-100">
            <div class="display-6">{{ $totalFeedback }}</div>
            <div class="text-muted small">Total feedback</div>
        </div>
    </div>
</div>

<div class="card p-3">
    <form method="GET" action="{{ route('admin.reviews') }}" class="row g-2 align-items-end mb-3">
        <div class="col-md-4">
            <label class="form-label small mb-0" for="filter-personnel">Personnel</label>
            <select id="filter-personnel" name="personnel_id" class="form-select">
                <option value="">All personnel</option>
                @foreach($personnelOptions as $member)
                    <option value="{{ $member->id }}" {{ $personnelId === $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-spa">Apply</button>
        </div>
    </form>

    @if($reviews->isEmpty())
        <p class="text-muted text-center py-4 mb-0">
            {{ $personnelId > 0 ? 'No feedback for this personnel yet.' : 'No feedback yet — feedback appears here after clients leave feedback on completed appointments.' }}
        </p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>Submitted</th><th>Client</th><th>Service</th><th>Personnel</th><th>Feedback</th><th>Appointment ID</th></tr>
                </thead>
                <tbody>
                    @foreach($reviews as $review)
                        <tr>
                            <td>{{ $review->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('admin.clients.show', $review->user) }}" class="fw-semibold">
                                    {{ $review->user->name }}
                                </a>
                            </td>
                            <td>{{ $review->appointment->displayServiceName() }}</td>
                            <td>{{ $review->appointment->personnel?->name ?? '—' }}</td>
                            <td style="max-width:280px;">{{ $review->comment ?? '—' }}</td>
                            <td>{{ $review->appointment->referenceCode() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $reviews->links() }}
    @endif
</div>
@endsection
