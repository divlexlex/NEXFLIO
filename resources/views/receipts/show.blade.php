@extends('layouts.receipt')

@section('title', 'Transaction Record')

@section('content')
<div class="nx-receipt-card">
    <div class="text-center mb-4">
        <div class="nx-receipt-brand">Perfect Nails</div>
        <div class="nx-receipt-sub">WELLNESS &amp; AESTHETICS</div>
        <p class="text-muted small mt-2 mb-0">Transaction Record</p>
    </div>

    <div class="nx-receipt-row">
        <span class="nx-receipt-label">Appointment ID</span>
        <strong>{{ $appointment->referenceCode() }}</strong>
    </div>
    <div class="nx-receipt-row">
        <span class="nx-receipt-label">Date &amp; time</span>
        <span>{{ $appointment->appointment_date->format('M j, Y') }} · {{ \Carbon\Carbon::parse($appointment->start_time)->format('g:i A') }}</span>
    </div>
    <div class="nx-receipt-row">
        <span class="nx-receipt-label">Service</span>
        <span>{{ $appointment->service->name ?? 'Service' }}{{ $appointment->variant_label ? ' — '.$appointment->variant_label : '' }}</span>
    </div>

    @if($downPayment)
        <div class="nx-receipt-row">
            <span class="nx-receipt-label">Down payment{{ $downPayment['method'] ? ' — '.$downPayment['method'] : '' }}</span>
            <span>₱{{ number_format($downPayment['amount'], 2) }}</span>
        </div>
    @endif
    @if($branchPaid)
        <div class="nx-receipt-row">
            <span class="nx-receipt-label">Paid at branch{{ $branchPaid['method'] ? ' — '.$branchPaid['method'] : '' }}</span>
            <span>₱{{ number_format($branchPaid['amount'], 2) }}</span>
        </div>
    @endif

    <hr>

    <div class="nx-receipt-row fw-bold">
        <span>Total amount</span>
        <span>₱{{ number_format($total, 2) }}</span>
    </div>

    <p class="text-muted small mt-4 mb-0">System-generated transaction record. Not a BIR-accredited official receipt (OR) for tax purposes.</p>

    <div class="d-flex justify-content-between gap-2 mt-4 no-print">
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Back</a>
        <button type="button" class="btn btn-spa" onclick="window.print()">Print / Save as PDF</button>
    </div>
</div>
@endsection
