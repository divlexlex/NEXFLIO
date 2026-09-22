{{-- Home Service Booking — Step 2: Client / Address Details (Phase 3B).
     Prefilled from the Client's real saved default address
     ($user->addresses()->where('is_default', true)->first(), see
     ClientAccountController@profile for the same pattern) when one exists —
     never re-asked for unnecessarily — but always editable for this
     specific booking. Editing here does NOT update the Client's saved
     client_addresses row; it only becomes this appointment's
     AppointmentAddress snapshot on Submit (see BookingController@homeStore).
     source_client_address_id is carried along purely as an optional trace
     back to which saved address (if any) this started from. --}}
@extends('layouts.public')

@php($homeSteps = ['Service', 'Address', 'Date & Time', 'Details', 'Review', 'Payment', 'Submitted'])

@section('title', 'Book Home Service — Address')

@section('content')
<header class="nx-hero">
    <div class="container py-5">
        <p class="nx-eyebrow mb-2">Home Service</p>
        <h1 class="mb-0">Client &amp; Address Details</h1>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-page); padding-top: 40px;">
    <div class="container">
        @include('partials.booking.progress', ['step' => 2, 'steps' => $homeSteps])

        @if($errors->any())
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="alert alert-danger">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="nx-booking-panel">
                    <p class="nx-eyebrow mb-3">Your Information</p>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Name</span>
                        <span class="nx-review-value">{{ $client->name }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Email</span>
                        <span class="nx-review-value">{{ $client->email }}</span>
                    </div>
                    @if($client->clientProfile?->mobile_number)
                        <div class="nx-review-row">
                            <span class="nx-review-label">Mobile</span>
                            <span class="nx-review-value">{{ $client->clientProfile->mobile_number }}</span>
                        </div>
                    @endif

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    <p class="nx-eyebrow mb-2">Service Address</p>
                    <p class="small nx-text-secondary mb-3">
                        @if($defaultAddress)
                            Prefilled from your saved default address. You can edit it below for this
                            booking only — your saved address won't be changed.
                        @else
                            You don't have a saved address yet. Enter where the specialist should visit
                            you — you can save this to your profile separately afterwards.
                        @endif
                    </p>

                    <form method="GET" action="{{ route('account.booking.home.schedule') }}">
                        <input type="hidden" name="service" value="{{ $service->id }}">
                        <input type="hidden" name="source_client_address_id" value="{{ $sourceClientAddressId }}">

                        <div class="mb-3">
                            <label for="street_address" class="form-label">Street / House / Unit / Building</label>
                            <input type="text" name="street_address" id="street_address" maxlength="255" required
                                   class="form-control @error('street_address') is-invalid @enderror"
                                   value="{{ old('street_address', $streetAddress) }}">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="barangay" class="form-label">Barangay</label>
                                <input type="text" name="barangay" id="barangay" maxlength="100" required
                                       class="form-control @error('barangay') is-invalid @enderror"
                                       value="{{ old('barangay', $barangay) }}">
                            </div>
                            <div class="col-sm-6">
                                <label for="city_municipality" class="form-label">City / Municipality</label>
                                <input type="text" name="city_municipality" id="city_municipality" maxlength="100" required
                                       class="form-control @error('city_municipality') is-invalid @enderror"
                                       value="{{ old('city_municipality', $cityMunicipality) }}">
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label for="province" class="form-label">Province</label>
                                <input type="text" name="province" id="province" maxlength="100" required
                                       class="form-control @error('province') is-invalid @enderror"
                                       value="{{ old('province', $province) }}">
                            </div>
                            <div class="col-sm-6">
                                <label for="postal_code" class="form-label">Postal Code <span class="nx-text-secondary" style="font-weight:400;">(optional)</span></label>
                                <input type="text" name="postal_code" id="postal_code" maxlength="10"
                                       class="form-control @error('postal_code') is-invalid @enderror"
                                       value="{{ old('postal_code', $postalCode) }}">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <a href="{{ route('account.booking.home.service.show', $service->id) }}" class="nx-btn nx-btn-outline">
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
