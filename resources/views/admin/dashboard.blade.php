@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<h1 class="h3 mb-4">Dashboard</h1>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card p-3">
            <div class="text-muted small">Today's Bookings</div>
            <div class="fs-2 fw-bold">{{ $todayBookings }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card p-3">
            <div class="text-muted small">Awaiting Verification</div>
            <div class="fs-2 fw-bold text-warning">{{ $awaitingVerification }}</div>
            <a href="{{ route('admin.payments') }}" class="small">Review queue →</a>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card p-3">
            <div class="text-muted small">Upcoming (Booked)</div>
            <div class="fs-2 fw-bold text-success">{{ $upcomingBooked }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card p-3">
            <div class="text-muted small">Revenue · {{ now()->format('F') }}</div>
            <div class="fs-2 fw-bold text-gold">₱{{ number_format($monthRevenue, 2) }}</div>
        </div>
    </div>
</div>

@if($lowStocks->isNotEmpty())
    <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle me-1"></i>Low stock:</strong>
        @foreach($lowStocks as $item)
            <span class="badge text-bg-warning ms-1">{{ $item->item_name }} ({{ $item->quantity }} {{ $item->unit }})</span>
        @endforeach
        <a href="{{ route('admin.inventory') }}" class="ms-2 small">Manage inventory →</a>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card p-3 h-100">
            <h2 class="h6 text-muted">Weekly Revenue (verified payments)</h2>
            <canvas id="revenueChart" height="130"></canvas>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card p-3 h-100">
            <h2 class="h6 text-muted">Bookings by Service (30 days)</h2>
            <canvas id="serviceChart" height="160"></canvas>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card p-3 h-100">
            <h2 class="h6 text-muted">Staff Utilization — Completed Services (30 days)</h2>
            <canvas id="staffChart" height="160"></canvas>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card p-3 h-100" id="insight-card">
            <h2 class="h6 text-muted"><i class="bi bi-stars me-1 text-gold"></i>AI Business Insights</h2>
            <p class="small mb-0" id="insight-body">
                <span class="text-muted">Loading insights…</span>
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    const spaGold = '#9C7A54';
    const spaEspresso = '#3D2817';
    const spaTan = '#E8D5C4';

    fetch("{{ route('admin.dashboard.data') }}", { headers: { 'Accept': 'application/json' } })
        .then(response => response.json())
        .then(data => {
            new Chart(document.getElementById('revenueChart'), {
                type: 'line',
                data: {
                    labels: data.revenue_weeks,
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: data.revenue_totals,
                        borderColor: spaGold,
                        backgroundColor: 'rgba(156,122,84,0.15)',
                        fill: true,
                        tension: 0.35,
                    }],
                },
                options: { plugins: { legend: { display: false } } },
            });

            new Chart(document.getElementById('serviceChart'), {
                type: 'doughnut',
                data: {
                    labels: data.service_labels,
                    datasets: [{
                        data: data.service_counts,
                        backgroundColor: [spaEspresso, spaGold, spaTan, '#E8B4B8', '#B08968', '#DDB892', '#7F5539', '#EDE0D4'],
                    }],
                },
            });

            new Chart(document.getElementById('staffChart'), {
                type: 'bar',
                data: {
                    labels: data.staff_labels,
                    datasets: [{
                        label: 'Completed services',
                        data: data.staff_counts,
                        backgroundColor: spaGold,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: { x: { ticks: { precision: 0 } } },
                },
            });
        });

    fetch("{{ route('admin.dashboard.insights') }}", { headers: { 'Accept': 'application/json' } })
        .then(response => response.json())
        .then(data => {
            const body = document.getElementById('insight-body');
            if (data.insight) {
                body.textContent = data.insight;
            } else if (data.configured === false) {
                body.innerHTML = '<span class="text-muted">Add a GEMINI_API_KEY to enable AI-summarized business insights.</span>';
            } else {
                body.innerHTML = '<span class="text-muted">Insights temporarily unavailable — try again later.</span>';
            }
        })
        .catch(() => {
            document.getElementById('insight-body').innerHTML =
                '<span class="text-muted">Insights temporarily unavailable.</span>';
        });
</script>
@endpush
