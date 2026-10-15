{{-- Branch Booking — Step 6: Payment + Submission (Phase 3A).
     POSTs to BookingController@store, which validates everything
     server-side again via StoreWebBranchBookingRequest (never trusts these
     hidden fields on their own) before writing anything. No payment
     gateway — manual proof upload + Management verification for online
     methods, same as the existing mobile booking flow. Cash needs no proof:
     the booking is submitted as a request the branch confirms, and the full
     amount is payable at the spa. When a reservation fee applies, only the fee is due online now
     and the balance settles at the branch. --}}
@extends('layouts.public')

@section('title', 'Book — Payment')

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
                            <h1 class="h5 mb-0">Payment</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 5])
                    </div>

                    <p class="nx-text-secondary mb-4 d-none" id="pay-lead-cash">
                        No payment needed now — your booking is confirmed immediately and you pay
                        <strong>₱{{ number_format($price, 2) }} in full at the branch</strong>.
                    </p>

                    <div class="nx-review-row">
                        <span class="nx-review-label">Service</span>
                        <span class="nx-review-value">{{ $service->name }}@if($variant) — {{ $variant->label }}@endif</span>
                    </div>
                    <div class="nx-review-row @if($fee === null) mb-4 @endif">
                        <span class="nx-review-label">Price</span>
                        <span class="nx-review-value">₱{{ number_format($price, 2) }}</span>
                    </div>
                    @if($fee !== null)
                        <div class="nx-review-row">
                            <span class="nx-review-label">Reservation fee (due now)</span>
                            <span class="nx-review-value">₱{{ number_format($fee, 2) }}</span>
                        </div>
                        <div class="nx-review-row mb-4">
                            <span class="nx-review-label">Balance (pay at the branch)</span>
                            <span class="nx-review-value">₱{{ number_format($balance, 2) }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('account.booking.branch.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="service_id" value="{{ $service->id }}">
                        @if($variant)<input type="hidden" name="variant_id" value="{{ $variant->id }}">@endif
                        <input type="hidden" name="appointment_date" value="{{ $date }}">
                        <input type="hidden" name="start_time" value="{{ $time }}">
                        <input type="hidden" name="personnel_id" value="{{ $personnel }}">
                        @if($notes)<input type="hidden" name="notes" value="{{ $notes }}">@endif

                        <label class="form-label" for="pay-method">Payment method</label>
                        <select name="method" id="pay-method" class="form-select mb-2" style="max-width: 260px;">
                            @foreach(config('payment.methods', []) as $value => $label)
                                @continue($isPackage && $value === 'cash')
                                <option value="{{ $value }}" @selected(old('method') === $value || (old('method') === null && $value === 'gcash'))>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if($isPackage)
                            <p class="small nx-text-secondary mb-4">Package bookings require the reservation fee to be paid online with proof of payment — Cash is not available for packages. The remaining balance is paid at the branch on your appointment day.</p>
                        @endif
                        <p class="small nx-text-secondary mb-4 d-none" id="pay-receiving"></p>

                        <div id="pay-proof-block">
                            <label class="form-label">Proof of payment</label>
                            <div class="nx-upload-control mb-2">
                                <input type="file" name="payment_proof" id="pay-proof" accept="image/*" required class="form-control @error('payment_proof') is-invalid @enderror">
                                <p class="small nx-text-secondary mt-2 mb-0">JPG or PNG, up to 5MB.</p>
                            </div>
                            {{-- Receipt check — inside the proof block so it
                                 hides/shows together with it when Cash is
                                 picked. Appears only while a file is selected;
                                 opens the full-size modal below for inspection
                                 instead of embedding a large image in the step.
                                 Reads as a plain accent-colored link, not a
                                 button: no border, no background. --}}
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
                        <div class="alert alert-info d-none" id="pay-cash-note">
                            <i class="bi bi-cash-coin me-1"></i>
                            Paying in cash — nothing to upload. This submits a booking request: the branch will
                            confirm your booking, and the full amount is payable at the spa.
                        </div>

                        <div class="d-flex justify-content-between flex-wrap gap-2 mt-4">
                            <a href="{{ route('account.booking.branch.review', array_filter(['service' => $service->id, 'variant' => $variant?->id, 'date' => $date, 'time' => $time, 'personnel' => $personnel, 'notes' => $notes])) }}" class="nx-btn nx-btn-outline">
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
        var method = document.getElementById('pay-method');
        var proofBlock = document.getElementById('pay-proof-block');
        var proofInput = document.getElementById('pay-proof');
        var cashNote = document.getElementById('pay-cash-note');
        var leadOnline = document.getElementById('pay-lead-online');
        var leadCash = document.getElementById('pay-lead-cash');
        var receiving = document.getElementById('pay-receiving');
        var receivingMap = @json(collect(config('payment.receiving', []))->map(fn ($r) => $r['detail'] ?? ''));
        var previewWrap = document.getElementById('pay-preview-wrap');
        var previewFull = document.getElementById('pay-preview-full');
        var previewUrl = null;
        if (!method) return;

        function clearPreview() {
            if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
            previewFull.removeAttribute('src');
            previewWrap.classList.add('d-none');
        }

        // Points the modal at the selected receipt (an image only — anything
        // else keeps the button row hidden) and reveals the "View image"
        // row. Always revokes the previous object URL first so the modal
        // never shows a stale file.
        function refreshPreview() {
            clearPreview();
            var file = proofInput.files && proofInput.files[0];
            if (!file || !file.type || file.type.indexOf('image/') !== 0) return;
            previewUrl = URL.createObjectURL(file);
            previewFull.src = previewUrl;
            previewWrap.classList.remove('d-none');
        }

        function sync() {
            var isCash = method.value === 'cash';
            proofBlock.classList.toggle('d-none', isCash);
            proofInput.required = !isCash;
            if (isCash) proofInput.value = '';
            refreshPreview();
            cashNote.classList.toggle('d-none', !isCash);
            if (leadOnline) leadOnline.classList.toggle('d-none', isCash);
            leadCash.classList.toggle('d-none', !isCash);
            var detail = receivingMap[method.value] || '';
            receiving.textContent = detail;
            receiving.classList.toggle('d-none', isCash || !detail);
        }

        method.addEventListener('change', sync);
        proofInput.addEventListener('change', refreshPreview);
        sync();
    })();
</script>
@endpush
