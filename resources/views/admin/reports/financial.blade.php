@extends('layouts.admin')

@section('title', 'Financial Reports')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h1 class="h3 mb-0">Financial Reports · {{ now()->year }}</h1>
    <div class="dropdown">
        <button class="btn btn-spa dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>Export
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('admin.reports.financial.export', 'csv') }}">
                <i class="bi bi-filetype-csv me-2"></i>CSV (spreadsheet)
            </a></li>
            <li><a class="dropdown-item" href="{{ route('admin.reports.financial.export', 'pdf') }}">
                <i class="bi bi-filetype-pdf me-2"></i>PDF
            </a></li>
        </ul>
    </div>
</div>

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
