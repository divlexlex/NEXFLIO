<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Financial Report {{ $year }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #2f2925; font-size: 12px; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #81776e; font-size: 10px; }
        h2 { font-size: 13px; margin: 22px 0 6px; border-bottom: 1px solid #e7ddd1; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #eee; }
        th { background: #f3ede4; }
        td.num, th.num { text-align: right; }
        .summary td:first-child { color: #81776e; }
        .summary td:last-child { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Perfect Nails — Financial Report</h1>
    <div class="muted">Year {{ $year }} &middot; Generated {{ now()->toDayDateTimeString() }}</div>

    <h2>Summary</h2>
    <table class="summary">
        <tr><td>Verified Revenue (YTD)</td><td>&#8369;{{ number_format($yearRevenue, 2) }}</td></tr>
        <tr><td>Commissions Accrued (YTD)</td><td>&#8369;{{ number_format($yearCommissions, 2) }}</td></tr>
        <tr><td>Net of Commissions</td><td>&#8369;{{ number_format($yearRevenue - $yearCommissions, 2) }}</td></tr>
    </table>

    <h2>Monthly Revenue — last 12 months</h2>
    <table>
        <thead><tr><th>Month</th><th class="num">Verified Revenue</th></tr></thead>
        <tbody>
            @foreach($months as $month)
                <tr>
                    <td>{{ $month['label'] }}</td>
                    <td class="num">&#8369;{{ number_format($month['revenue'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Top Services by Revenue ({{ $year }})</h2>
    <table>
        <thead><tr><th>Service</th><th class="num">Completed Sessions</th><th class="num">Revenue</th></tr></thead>
        <tbody>
            @forelse($topServices as $name => $row)
                <tr>
                    <td>{{ $name }}</td>
                    <td class="num">{{ $row['count'] }}</td>
                    <td class="num">&#8369;{{ number_format($row['revenue'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No completed services this year.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
