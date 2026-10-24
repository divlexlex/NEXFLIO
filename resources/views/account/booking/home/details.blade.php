{{-- Home Service Booking — Step 4: Appointment Details (Phase 3B). Client
     identity is read from the authenticated account, not re-entered — the
     service address was already collected in Step 2. --}}
@extends('layouts.public')

@php($homeSteps = ['Service', 'Address', 'Date & Time', 'Details', 'Review', 'Payment', 'Submitted'])

@section('title', 'Book Home Service — Appointment Details')

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-7">
                <div class="nx-booking-panel">
                    <div class="nx-booking-panel-header">
                        <div>
                            <p class="nx-eyebrow mb-1">HOME SERVICE</p>
                            <h1 class="h5 mb-0">Appointment Details</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 4, 'steps' => $homeSteps])
                    </div>

                    <p class="nx-eyebrow mb-3">Your Information</p>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Name</span>
                        <span class="nx-review-value">{{ $client->name }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Email</span>
                        <span class="nx-review-value">{{ $client->email }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Service address</span>
                        <span class="nx-review-value">{{ collect([$address['street_address'], $address['barangay'], $address['city_municipality'], $address['province']])->filter()->implode(', ') }}{{ $address['postal_code'] ? ' ' . $address['postal_code'] : '' }}</span>
                    </div>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    <form method="GET" action="{{ route('account.booking.home.review') }}">
                        @include('partials.booking.carry-fields', ['service' => $service->id, 'date' => $date, 'time' => $time, 'personnel' => $personnel, 'staff' => $staff ?? [], 'extras' => $extras, 'address' => $address])

                        @if(! empty($extras))
                            <div class="nx-review-row">
                                <span class="nx-review-label">Add service</span>
                                <span class="nx-review-value">{{ count($extras) }}</span>
                            </div>
                        @endif
                        @if($service->variants->isEmpty())
                            <p class="small nx-text-secondary mt-2 mb-3">
                                Down payment for this service(s) — deducted from your total, balance paid to the specialist at your home.@if(! empty($extras)) Each extra adds its own configured down payment.@endif
                            </p>
                        @endif

                        <div class="alert alert-warning py-2 px-3 small mt-2 mb-3" role="note">
                            <i class="bi bi-geo-alt-fill me-1"></i><strong>Travel fee excluded.</strong> Travel rate will be based on the Move It rate.
                        </div>

                        <label for="notes" class="form-label">Notes for the specialist <span class="nx-text-secondary" style="font-weight:400;">(optional)</span></label>
                        <textarea name="notes" id="notes" rows="4" maxlength="1000" class="form-control" placeholder="Landmarks, gate/unit access instructions, or anything the specialist should know?">{{ $notes }}</textarea>

                        <div class="d-flex justify-content-between flex-wrap gap-2 mt-4">
                            <a href="{{ route('account.booking.home.schedule', array_merge(['service' => $service->id, 'date' => $date, 'extras' => ! empty($extras) ? collect($extras)->map(fn ($extra) => array_filter(['service_id' => $extra['service']->id, 'variant_label' => $extra['variant_label'], 'personnel_id' => $extra['personnel_id'], 'start_time' => $extra['start_time'] ?? null], fn ($value) => $value !== null && $value !== ''))->all() : null], $address)) }}" class="nx-btn nx-btn-outline">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </a>
                            <button type="submit" class="nx-btn nx-btn-primary">
                                Continue <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
