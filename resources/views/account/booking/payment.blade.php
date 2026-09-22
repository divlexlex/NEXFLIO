{{-- Branch Booking — Step 6: Proof of Payment + Submission (Phase 3A).
     POSTs to BookingController@store, which validates everything
     server-side again via StoreWebBranchBookingRequest (never trusts these
     hidden fields on their own) before writing anything. No payment
     gateway — manual proof upload + Management verification, same as the
     existing mobile booking flow. --}}
@extends('layouts.public')

@section('title', 'Book — Payment Proof')

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-7">
                @if($errors->any())
                    <div class="alert alert-danger">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="nx-booking-panel">
                    <div class="nx-booking-panel-header">
                        <div>
                            <p class="nx-eyebrow mb-1">Branch Booking</p>
                            <h1 class="h5 mb-0">Proof of Payment</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 5])
                    </div>

                    <p class="nx-text-secondary mb-4">
                        Send your reservation fee via GCash or bank transfer, then upload a screenshot or
                        photo of the receipt below. Our team verifies payments manually — you'll be
                        notified as soon as your booking is confirmed.
                    </p>

                    <form method="POST" action="{{ route('account.booking.branch.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="service_id" value="{{ $service->id }}">
                        <input type="hidden" name="appointment_date" value="{{ $date }}">
                        <input type="hidden" name="start_time" value="{{ $time }}">
                        <input type="hidden" name="personnel_id" value="{{ $personnel }}">
                        @if($notes)<input type="hidden" name="notes" value="{{ $notes }}">@endif

                        <label class="form-label">Payment method</label>
                        <select name="method" class="form-select mb-4" style="max-width: 260px;">
                            <option value="gcash" selected>GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="card">Card</option>
                        </select>

                        <label class="form-label">Proof of payment</label>
                        <div class="nx-upload-control mb-2">
                            <input type="file" name="payment_proof" accept="image/*" required class="form-control @error('payment_proof') is-invalid @enderror">
                            <p class="small nx-text-secondary mt-2 mb-0">JPG or PNG, up to 5MB.</p>
                        </div>
                        @error('payment_proof')
                            <div class="text-danger small mb-3">{{ $message }}</div>
                        @enderror

                        <div class="d-flex justify-content-between flex-wrap gap-2 mt-4">
                            <a href="{{ route('account.booking.branch.review', ['service' => $service->id, 'date' => $date, 'time' => $time, 'personnel' => $personnel, 'notes' => $notes]) }}" class="nx-btn nx-btn-outline">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </a>
                            <button type="submit" class="nx-btn nx-btn-primary">
                                Submit Booking Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
