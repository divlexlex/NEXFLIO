@extends('layouts.admin')

@section('title', 'Billing')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Billing &amp; Payroll</h1>
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="small text-muted">Month</label>
        <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm w-auto"
               onchange="this.form.submit()">
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card p-3">
            <div class="text-muted small">Verified Revenue · {{ $month }}</div>
            <div class="fs-2 fw-bold text-gold">₱{{ number_format($revenueTotal, 2) }}</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3">
            <div class="text-muted small">Payroll (base + commission) · {{ $month }}</div>
            <div class="fs-2 fw-bold">₱{{ number_format($payrollTotal, 2) }}</div>
        </div>
    </div>
</div>

<div class="card p-3 mb-4">
    <h2 class="h6 text-muted mb-3">Staff Payroll — base pay + 10% commission per completed service</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr><th>Staff</th><th>Base Pay</th><th>Commission</th><th class="text-end">Total</th></tr>
            </thead>
            <tbody>
                @forelse($staffPayroll as $row)
                    <tr>
                        <td>{{ $row['user']->name }}</td>
                        <td>₱{{ number_format($row['base_pay'], 2) }}</td>
                        <td>₱{{ number_format($row['commission'], 2) }}</td>
                        <td class="text-end fw-bold">₱{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No staff on record.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3">
    <h2 class="h6 text-muted mb-3">Verified Payments</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr><th>Verified At</th><th>Client</th><th>Service</th><th>Method</th><th>Verified By</th><th class="text-end">Amount</th></tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td class="text-muted small">{{ $payment->verified_at?->format('M j, g:i A') }}</td>
                        <td>{{ $payment->appointment?->clientName() ?? '—' }}</td>
                        <td>{{ $payment->appointment?->service?->name ?? '—' }}</td>
                        <td>{{ strtoupper($payment->method) }}</td>
                        <td>{{ $payment->verifier->name ?? '—' }}</td>
                        <td class="text-end">₱{{ number_format($payment->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No verified payments this month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
