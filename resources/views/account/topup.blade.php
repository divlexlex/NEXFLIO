{{-- Client top-up (step 2B): add only the shortfall on a rejected booking
     instead of rebooking. The amount is display-only — storeTopup()
     recomputes it server-side and re-checks every canContinueBooking()
     condition plus the slot collision. Method excludes Cash (a top-up is
     an online proof payment by definition); proof upload reuses the
     booking payment step's UI including the borderless "View image"
     check. --}}
@extends('layouts.public')

@section('title', 'Continue Booking — Top Up')

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
                            <p class="nx-eyebrow mb-1">My Bookings</p>
                            <h1 class="h5 mb-0">Continue to Book This</h1>
                        </div>
                    </div>

                    <p class="nx-text-secondary mb-4">
                        Your earlier payment fell short, but the
                        ₱{{ number_format($appointment->partialReceiptTotal(), 2) }} the branch received is
                        kept on this booking — just add the remaining shortfall below instead of
                        booking again.
                    </p>

                    <div class="nx-review-row">
                        <span class="nx-review-label">Appointment ID</span>
                        <span class="nx-review-value">{{ $appointment->referenceCode() }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Service</span>
                        <span class="nx-review-value">{{ $appointment->displayServiceName() }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Date</span>
                        <span class="nx-review-value">{{ $appointment->appointment_date->format('F j, Y') }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Time</span>
                        <span class="nx-review-value">{{ \Illuminate\Support\Carbon::parse($appointment->start_time)->format('g:i A') }}</span>
                    </div>
                    <div class="nx-review-row mb-4">
                        <span class="nx-review-label">Amount to add</span>
                        <span class="nx-review-value">₱{{ number_format($shortfall, 2) }}</span>
                    </div>

                    <form method="POST" action="{{ route('account.bookings.topup.store', $appointment->id) }}" enctype="multipart/form-data">
                        @csrf

                        <label class="form-label" for="pay-method">Payment method</label>
                        <select name="method" id="pay-method" class="form-select mb-4" style="max-width: 260px;">
                            @foreach(config('payment.methods', []) as $value => $label)
                                @continue($value === 'cash')
                                <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <div id="pay-proof-block">
                            <label class="form-label">Proof of payment</label>
                            <div class="nx-upload-control mb-2">
                                <input type="file" name="payment_proof" id="pay-proof" accept="image/*" required class="form-control @error('payment_proof') is-invalid @enderror">
                                <p class="small nx-text-secondary mt-2 mb-0">JPG or PNG, up to 5MB.</p>
                            </div>
                            <style>
                                .nx-preview-link {
                                    background: none; border: none; padding: 0;
                                    font-size: 13px; font-weight: 500;
                                    color: var(--nx-accent); text-decoration: none;
                                    cursor: pointer;
                                }
                                .nx-preview-link:hover {
                                    color: var(--nx-accent); text-decoration: underline;
                                    filter: brightness(1.15);
                                }
                            </style>
                            <div class="mt-2 d-none" id="pay-preview-wrap">
                                <button type="button" class="nx-preview-link me-2" data-bs-toggle="modal" data-bs-target="#pay-preview-modal">
                                    <i class="bi bi-eye me-1"></i>View image
                                </button>
                                <span class="small nx-text-secondary">Double-check your receipt before submitting.</span>
                            </div>
                            @error('payment_proof')
                                <div class="text-danger small mb-3">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between flex-wrap gap-2 mt-4">
                            <a href="{{ route('account.bookings') }}" class="nx-btn nx-btn-outline">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </a>
                            <button type="submit" class="nx-btn nx-btn-primary">
                                Submit Top-Up
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Full-size receipt preview — read-only inspection, closing it changes
         nothing about the selected file or the form. --}}
    <div class="modal fade" id="pay-preview-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 nx-body" style="border-radius: var(--nx-radius-lg); overflow: hidden;">
                <div class="modal-header" style="border-color: var(--nx-border);">
                    <h2 class="h5 mb-0">Proof of payment</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2 text-center">
                    <img id="pay-preview-full" src="" alt="Proof of payment full size" class="img-fluid w-100">
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    (function () {
        var proofInput = document.getElementById('pay-proof');
        var previewWrap = document.getElementById('pay-preview-wrap');
        var previewFull = document.getElementById('pay-preview-full');
        var previewUrl = null;
        if (!proofInput) return;

        function clearPreview() {
            if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
            previewFull.removeAttribute('src');
            previewWrap.classList.add('d-none');
        }

        function refreshPreview() {
            clearPreview();
            var file = proofInput.files && proofInput.files[0];
            if (!file || !file.type || file.type.indexOf('image/') !== 0) return;
            previewUrl = URL.createObjectURL(file);
            previewFull.src = previewUrl;
            previewWrap.classList.remove('d-none');
        }

        proofInput.addEventListener('change', refreshPreview);
    })();
</script>
@endpush
