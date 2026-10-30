@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<h1 class="h3 mb-4">Dashboard</h1>

{{-- Refreshable region: swapped in place by nx-refresh.js (no page reload).
     The charts below are deliberately excluded — their canvases are bound
     to Chart.js instances that a markup swap would destroy. --}}
<div id="nx-refresh-region">
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

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card p-3">
            <div class="text-muted small">Revenue · Week to Date</div>
            <div class="fs-2 fw-bold text-gold">₱{{ number_format($wtdRevenue, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card p-3">
            <div class="text-muted small">Revenue · Month to Date</div>
            <div class="fs-2 fw-bold text-gold">₱{{ number_format($mtdRevenue, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card p-3">
            <div class="text-muted small">Revenue · Year to Date</div>
            <div class="fs-2 fw-bold text-gold">₱{{ number_format($ytdRevenue, 2) }}</div>
        </div>
    </div>
</div>

@if($isOwner)
<div class="card p-3 mb-4" id="top-services-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="h6 text-muted mb-0">Top Services ({{ $topPeriodLabel }})</h2>
        <ul class="nav nav-pills">
            <li class="nav-item"><a class="nav-link {{ $topPeriod === 'wtd' ? 'active' : '' }}" href="{{ route('admin.dashboard', array_filter(['top_period' => 'wtd', 'rev_from' => $revFrom, 'rev_to' => $revTo])) }}">WTD</a></li>
            <li class="nav-item"><a class="nav-link {{ $topPeriod === 'mtd' ? 'active' : '' }}" href="{{ route('admin.dashboard', array_filter(['top_period' => 'mtd', 'rev_from' => $revFrom, 'rev_to' => $revTo])) }}">MTD</a></li>
            <li class="nav-item"><a class="nav-link {{ $topPeriod === 'ytd' ? 'active' : '' }}" href="{{ route('admin.dashboard', array_filter(['top_period' => 'ytd', 'rev_from' => $revFrom, 'rev_to' => $revTo])) }}">YTD</a></li>
        </ul>
    </div>
    @if($topServices->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr><th>#</th><th>Service</th><th>Completed</th><th class="text-end">Revenue</th></tr>
            </thead>
            <tbody>
                @foreach($topServices as $name => $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:38px;height:38px;background:#3A2F27;color:#D4A94E;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-weight:600;flex-shrink:0;">{{ $row['initials'] }}</span>
                                <span>
                                    <span class="d-block">{{ $name }}</span>
                                    @if($row['category'] !== '')
                                        <small class="text-muted">{{ $row['category'] }}</small>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td>{{ $row['count'] }}</td>
                        <td class="text-end">₱{{ number_format($row['revenue'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="text-muted small">Entry: at least {{ $topThreshold }} completed services in the period.</div>
    @else
    <p class="text-muted mt-3 mb-1">No service has reached {{ $topThreshold }} completed services in this period yet.</p>
    @if($topNearMiss->isNotEmpty())
    <details class="mt-2">
        <summary class="btn btn-outline-warning btn-sm">Track service status</summary>
        <div class="table-responsive mt-2">
            <table class="table table-sm align-middle">
                <thead>
                    <tr><th>Closest services</th><th>Completed</th><th>More needed</th></tr>
                </thead>
                <tbody>
                    @foreach($topNearMiss as $name => $row)
                        <tr>
                            <td>{{ $name }}</td>
                            <td>{{ $row['count'] }}</td>
                            <td>{{ $topThreshold - $row['count'] }} more</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
    @endif
    @endif
</div>
@endif
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card p-3 h-100" id="revenue-overview-card">
            <h2 class="h6 text-muted">@if($revFilterLabel) Revenue · {{ $revFilterLabel }} (verified payments) @else Weekly Revenue (verified payments) @endif</h2>
            {{-- Revenue overview custom date filter (Lyka, 2026-10-30):
                 a specific From–To window for peak-season tracking.
                 Revenue-only: WTD/MTD/YTD cards and Top Services are
                 not changed by this filter. Full-page GET, so the
                 Chart.js chart below reloads with the same window. --}}
            <form method="GET" action="{{ route('admin.dashboard') }}" id="revenue-filter-form" class="d-flex flex-wrap align-items-end gap-2 mb-2">
                <input type="hidden" name="top_period" value="{{ $topPeriod }}">
                <div>
                    <label for="rev_from" class="form-label small text-muted mb-0">From</label>
                    <input type="date" id="rev_from" name="rev_from" value="{{ $revFromInput }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label for="rev_to" class="form-label small text-muted mb-0">To</label>
                    <input type="date" id="rev_to" name="rev_to" value="{{ $revToInput }}" class="form-control form-control-sm">
                </div>
                <button type="submit" class="btn btn-warning btn-sm">Apply filter</button>
                @if($revFilterLabel)
                    <a href="{{ route('admin.dashboard', ['top_period' => $topPeriod]) }}" data-dashboard-clear class="btn btn-outline-secondary btn-sm">Clear</a>
                @endif
            </form>
            @if($revFilterError)
                <div class="alert alert-warning py-2 small mb-2">{{ $revFilterError }}</div>
            @endif
            @if($revFilterLabel)
                <div class="d-flex flex-wrap gap-3 mb-2">
                    <div><span class="text-muted small">Revenue in selected range</span><div class="fw-bold text-gold">₱{{ number_format($revFilterTotal, 2) }}</div></div>
                    <div><span class="text-muted small">Peak day</span><div class="fw-bold">@if($revPeak) {{ $revPeak['label'] }} — ₱{{ number_format($revPeak['total'], 2) }} @else No verified revenue in this range. @endif</div></div>
                </div>
            @endif
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

    // Pass the revenue custom date filter to the chart endpoint so
    // the chart shows the same window as the page (daily totals);
    // with no valid filter the endpoint returns the weekly view.
    function revenueChartQuery() {
        const chartParams = new URLSearchParams(window.location.search);
        return (chartParams.get('rev_from') && chartParams.get('rev_to'))
            ? '?rev_from=' + encodeURIComponent(chartParams.get('rev_from')) + '&rev_to=' + encodeURIComponent(chartParams.get('rev_to'))
            : '';
    }

    let revenueChart = null;

    function renderRevenueChart(labels, totals) {
        if (revenueChart) { revenueChart.destroy(); revenueChart = null; }
        const canvas = document.getElementById('revenueChart');
        if (!canvas) return;
        revenueChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: totals,
                    borderColor: spaGold,
                    backgroundColor: 'rgba(156,122,84,0.15)',
                    fill: true,
                    tension: 0.35,
                }],
            },
            options: { plugins: { legend: { display: false } } },
        });
    }

    function loadRevenueChart() {
        return fetch("{{ route('admin.dashboard.data') }}" + revenueChartQuery(), { headers: { 'Accept': 'application/json' } })
            .then(response => response.json())
            .then(data => { renderRevenueChart(data.revenue_weeks, data.revenue_totals); });
    }

    // Dashboard AJAX (sha, 2026-10-30): period pills, revenue
    // filter Apply, and Clear update in place — no full browser
    // reload. Same pattern as nx-refresh.js: fetch this page's HTML,
    // swap only the period and revenue cards, then re-render
    // the revenue chart. Delegated listeners survive the swaps, so
    // the new pills/form keep working without rebinding. Any failure
    // falls back to a real navigation, so the user is never stuck
    // on a half-updated page.
    let dashboardAjaxLoading = false;

    function dashboardAjaxLoad(url, push) {
        if (dashboardAjaxLoading) return;
        dashboardAjaxLoading = true;
        const target = new URL(url, window.location.origin);
        const pathQuery = target.pathname + target.search;
        fetch(pathQuery, { credentials: 'same-origin', headers: { 'Accept': 'text/html' } })
            .then(function (response) {
                if (!response.ok) throw new Error('Dashboard fetch failed');
                return response.text();
            })
            .then(function (html) {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const freshTop = doc.getElementById('top-services-card');
                const freshRevenue = doc.getElementById('revenue-overview-card');
                const currentTop = document.getElementById('top-services-card');
                const currentRevenue = document.getElementById('revenue-overview-card');
                // Managers have no period card; only the revenue
                // card is required. Anything else means the server did
                // not return the dashboard (e.g. login page).
                if (!freshRevenue || !currentRevenue) {
                    window.location.href = pathQuery;
                    return;
                }
                if (revenueChart) { revenueChart.destroy(); revenueChart = null; }
                if (freshTop && currentTop) { currentTop.innerHTML = freshTop.innerHTML; }
                currentRevenue.innerHTML = freshRevenue.innerHTML;
                if (push) { window.history.pushState(null, '', pathQuery); }
                return loadRevenueChart();
            })
            .catch(function () {
                window.location.href = pathQuery;
            })
            .finally(function () {
                dashboardAjaxLoading = false;
            });
    }

    document.addEventListener('click', function (event) {
        const pill = event.target.closest('#top-services-card .nav-link');
        if (pill) {
            event.preventDefault();
            dashboardAjaxLoad(pill.href, true);
            return;
        }
        const clear = event.target.closest('#revenue-overview-card a[data-dashboard-clear]');
        if (clear) {
            event.preventDefault();
            dashboardAjaxLoad(clear.href, true);
        }
    });

    document.addEventListener('submit', function (event) {
        if (event.target.id !== 'revenue-filter-form') return;
        event.preventDefault();
        const params = new URLSearchParams(new FormData(event.target));
        // Both dates empty = Clear, not an error: drop the params so
        // the server shows the default weekly view.
        if (!params.get('rev_from') && !params.get('rev_to')) {
            params.delete('rev_from');
            params.delete('rev_to');
        }
        const actionUrl = new URL(event.target.action, window.location.origin);
        actionUrl.search = params.toString();
        dashboardAjaxLoad(actionUrl.toString(), true);
    });

    window.addEventListener('popstate', function () {
        dashboardAjaxLoad(window.location.pathname + window.location.search, false);
    });

    fetch("{{ route('admin.dashboard.data') }}" + revenueChartQuery(), { headers: { 'Accept': 'application/json' } })
        .then(response => response.json())
        .then(data => {
            renderRevenueChart(data.revenue_weeks, data.revenue_totals);

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
