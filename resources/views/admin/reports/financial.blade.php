@extends('layouts.admin')

@section('title', 'Financial Reports')

@section('content')
<h1 class="h3 mb-4">Financial Reports · {{ now()->year }}</h1>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3">
            <div class="text-muted small">Verified Revenue (YTD)</div>
            <div class="fs-2 fw-bold text-gold">₱{{ number_format($yearRevenue, 2) }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3">
            <div class="text-muted small">Commissions Accrued (YTD)</div>
            <div class="fs-2 fw-bold">₱{{ number_format($yearCommissions, 2) }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3">
            <div class="text-muted small">Net of Commissions</div>
            <div class="fs-2 fw-bold text-success">₱{{ number_format($yearRevenue - $yearCommissions, 2) }}</div>
        </div>
    </div>
</div>

<div class="card p-3 mb-4">
    <h2 class="h6 text-muted">Monthly Revenue — last 12 months</h2>
    <canvas id="monthlyChart" height="90"></canvas>
</div>

<div class="card p-3">
    <h2 class="h6 text-muted mb-3">Top Services by Revenue ({{ now()->year }})</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr><th>Service</th><th>Completed Sessions</th><th class="text-end">Revenue</th></tr>
            </thead>
            <tbody>
                @forelse($topServices as $name => $row)
                    <tr>
                        <td>{{ $name }}</td>
                        <td>{{ $row['count'] }}</td>
                        <td class="text-end">₱{{ number_format($row['revenue'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">No completed services this year.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: @json(array_column($months, 'label')),
            datasets: [{
                label: 'Revenue (₱)',
                data: @json(array_column($months, 'revenue')),
                backgroundColor: '#9C7A54',
                borderRadius: 4,
            }],
        },
        options: { plugins: { legend: { display: false } } },
    });
</script>
@endpush
