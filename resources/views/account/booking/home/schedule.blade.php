{{-- Home Service Booking — Step 3: Date, Personnel & Time (Phase 3B).

     Same list-of-services builder as Branch: card #1 is the main service
     (server-rendered personnel/time), added services each get their own
     personnel and start time. Availability is still server-side; store()
     re-validates every segment. Address fields are carried to every step. --}}
@extends('layouts.public')

@php
    $homeSteps = ['Service', 'Address', 'Date & Time', 'Details', 'Review', 'Payment', 'Submitted'];
@endphp

@section('title', 'Book Home Service — Date & Time')

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                @if($errors->any())
                    <div class="alert alert-danger">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="nx-booking-panel mb-4">
                    <div class="nx-booking-panel-header">
                        <div>
                            <p class="nx-eyebrow mb-1">Home Service</p>
                            <h1 class="h5 mb-0">Choose Date &amp; Time</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 3, 'steps' => $homeSteps])
                    </div>

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="nx-card-media" style="width: 64px; height: 64px; border-radius: var(--nx-radius-md); flex-shrink: 0;">
                            @if($service->image_url)
                                <img src="{{ $service->image_url }}" alt="{{ $service->name }}">
                            @else
                                <i class="bi bi-image opacity-50"></i>
                            @endif
                        </div>
                        <div>
                            <p class="fw-semibold mb-0">{{ $service->name }}</p>
                            <p class="small nx-text-secondary mb-0">{{ $service->duration_minutes }} mins &middot; ₱{{ number_format($service->price, 2) }}</p>
                        </div>
                    </div>

                    <div id="scheduleList" class="nx-svc-list">
                        {{-- CARD #1 — MAIN SERVICE (server-rendered, unchanged) --}}
                        <div class="nx-svc-card is-active" id="svcCardMain" data-card
                             data-service-id="{{ $service->id }}"
                             data-name="{{ $service->name }}"
                             data-category="{{ $service->category }}"
                             data-duration="{{ (int) $service->duration_minutes }}"
                             data-price="{{ (float) $service->price }}"
                             data-downpayment="{{ $service->down_payment !== null ? (float) $service->down_payment : '' }}"
                             data-variant-id=""
                             data-variant-label=""
                             data-multistaff="{{ $isMultiStaff ? '1' : '0' }}"
                             data-time="{{ $selectedTime ?? '' }}">
                            <div class="nx-svc-card-finished fin-bar d-none" data-card-finished>
                                <span class="fin-check">✓</span>
                                <span data-finished-summary></span>
                                <span class="fin-actions">
                                    <span class="nx-svc-card-edit fin-done">✓ Done</span>
                                </span>
                            </div>

                            <div class="nx-svc-card-body" data-card-body>
                                <p class="nx-eyebrow mb-2">Preferred Spa Personnel</p>
                                <form method="GET" action="{{ route('account.booking.home.schedule') }}" id="personnelForm">
                                    <input type="hidden" name="service" value="{{ $service->id }}">
                                    <input type="hidden" name="date" value="{{ $date }}">
                                    @include('partials.booking.carry-fields', ['service' => null, 'address' => $address])
                                    @include('partials.booking.personnel-picker')
                                </form>

                                <p class="nx-eyebrow mb-2 mt-3">Time</p>
                                @if(!$personnelParam)
                                    <p class="nx-text-secondary small mb-0">Choose Spa Personnel above to see available times.</p>
                                @elseif(empty($slots))
                                    <p class="nx-text-secondary small mb-0">No time slots configured for this service.</p>
                                @elseif(collect($slots)->where('available', true)->isEmpty())
                                    <div class="alert alert-warning mb-0">No open slots left for this date. Please try another date or personnel.</div>
                                @else
                                    <div class="nx-slot-grid">
                                        @foreach($slots as $slot)
                                            @if($slot['available'])
                                                <button type="button"
                                                        class="nx-slot-btn {{ $selectedTime === $slot['time'] ? 'is-selected' : '' }}"
                                                        data-slot-time="{{ $slot['time'] }}">
                                                    {{ \Illuminate\Support\Carbon::parse($slot['time'])->format('g:i A') }}
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    {{-- DATE --}}
                    <form method="GET" action="{{ route('account.booking.home.schedule') }}" id="dateForm">
                        <input type="hidden" name="service" value="{{ $service->id }}">
                        @include('partials.booking.carry-fields', ['service' => null, 'address' => $address])
                        <label class="form-label nx-eyebrow">Date</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="date" name="date" value="{{ $date }}" min="{{ $minDate }}" max="{{ $maxDate }}"
                                   class="form-control" style="max-width: 220px;" onchange="document.getElementById('dateForm').submit()" required>
                            <button type="submit" class="nx-btn nx-btn-outline">Update</button>
                        </div>
                    </form>
                </div>

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <a href="{{ route('account.booking.home.address', array_merge(['service' => $service->id], $address)) }}" class="nx-btn nx-btn-outline">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                    <div class="ms-auto d-flex gap-2 d-none" id="scheduleActionBar">
                        <button type="button" class="nx-btn nx-btn-primary" id="scheduleContinueBtn">Continue &rarr;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SUBMIT FORM — JS maps the list onto the existing field names; address
         fields are rendered once here so every details link keeps them. --}}
    <form method="GET" action="{{ route('account.booking.home.details') }}" id="scheduleSubmitForm" class="d-none">
        @foreach($address as $field => $value)
            @if($value !== null && $value !== '')
                <input type="hidden" name="{{ $field }}" value="{{ $value }}" data-address-field>
            @endif
        @endforeach
    </form>

</section>
@endsection

@push('head')
<style>
    .nx-svc-list { display: flex; flex-direction: column; gap: 16px; }
    .nx-svc-card { border: 1px solid var(--nx-border); border-radius: var(--nx-radius-lg); padding: 18px; background: transparent; transition: box-shadow .2s ease, border-color .2s ease, background .2s ease, transform .2s ease; }
    .nx-svc-card.is-active { border-color: var(--nx-accent); box-shadow: 0 0 0 1px var(--nx-accent); }
    .nx-svc-card.is-finished { padding: 12px 16px; background: rgba(0,0,0,.025); }
    .nx-svc-card-finished { display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 14px; cursor: pointer; }
    .nx-svc-card-edit { font-size: 12px; color: var(--nx-accent); }
    .nx-svc-card-body { margin-top: 14px; }
    .nx-svc-personnel { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 4px; }
    .nx-svc-personnel .nx-select-card { padding: 8px 12px; }
    .nx-slot-btn { transition: background .18s ease, color .18s ease, border-color .18s ease, transform .12s ease; }
    .nx-slot-btn.is-selected { transform: translateY(-1px); }
    .fin-bar { display: flex; align-items: center; gap: 12px; background: rgba(0,0,0,.28); border: 1px solid var(--nx-border); border-radius: 12px; padding: 12px 16px; }
    .fin-check { width: 26px; height: 26px; border-radius: 50%; border: 1.5px solid var(--nx-gold, #E0B774); color: var(--nx-gold, #E0B774); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; background: rgba(224,183,116,.10); }
    .fin-actions { margin-left: auto; display: inline-flex; align-items: center; gap: 14px; }
    .fin-done { letter-spacing: 2px; font-size: 10.5px; text-transform: uppercase; font-weight: 600; color: var(--nx-gold, #E0B774); border: 1px solid var(--nx-gold, #E0B774); background: rgba(224,183,116,.10); padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        var list = document.getElementById('scheduleList');
        var mainCard = document.getElementById('svcCardMain');
        var submitForm = document.getElementById('scheduleSubmitForm');
        var actionBar = document.getElementById('scheduleActionBar');
        var continueBtn = document.getElementById('scheduleContinueBtn');
        if (!list || !mainCard || !submitForm) return;

        var bookingDate = @json($date);
        var activeCard = mainCard;
        function minutesOf(time) {
            var parts = String(time || '00:00').split(':');
            return parseInt(parts[0], 10) * 60 + parseInt(parts[1] || '0', 10);
        }
        function labelOf(time) {
            var m = minutesOf(time);
            var h = Math.floor(m / 60) % 24;
            var mm = m % 60;
            var ampm = h >= 12 ? 'PM' : 'AM';
            var hh = h % 12 || 12;
            return hh + ':' + String(mm).padStart(2, '0') + ' ' + ampm;
        }
        function getCards() { return Array.prototype.slice.call(list.querySelectorAll('[data-card]')); }

        function mainPersonnelLabel() {
            if (mainCard.dataset.multistaff === '1') {
                var names = [];
                mainCard.querySelectorAll('input[name^="staff["]:checked').forEach(function (radio) {
                    names.push(radio.dataset.personnelName || 'No preferred personnel');
                });
                return names.length ? names.join(', ') : 'No preferred personnel';
            }
            var checked = mainCard.querySelector('input[name="personnel"]:checked');
            return checked ? (checked.dataset.personnelName || 'No preferred personnel') : 'No preferred personnel';
        }

        function personnelLabel(card) {
            if (card === mainCard) return mainPersonnelLabel();
            var checked = card.querySelector('[data-card-personnel]:checked');
            if (!checked || checked.value === '') return 'No preferred personnel';
            return checked.dataset.personnelName || 'No preferred personnel';
        }

        function personnelValue(card) {
            if (card === mainCard) {
                if (card.dataset.multistaff === '1') return '';
                var mainChecked = card.querySelector('input[name="personnel"]:checked');
                return mainChecked ? mainChecked.value : '';
            }
            var checked = card.querySelector('[data-card-personnel]:checked');
            return checked ? checked.value : '';
        }

        function setSelectedTime(card, time) {
            card.dataset.time = time;
            card.querySelectorAll('[data-slot-time]').forEach(function (button) {
                button.classList.toggle('is-selected', button.dataset.slotTime === time);
            });
        }

        function finishCard(card) {
            if (!card.dataset.time) return;
            card.classList.add('is-finished');
            card.classList.remove('is-active');
            var body = card.querySelector('[data-card-body]');
            if (body) body.classList.add('d-none');
            var finished = card.querySelector('[data-card-finished]');
            if (finished) finished.classList.remove('d-none');
            var summary = card.querySelector('[data-finished-summary]');
            if (summary) {
                summary.textContent = card.dataset.name + ' — ' + personnelLabel(card) + ' · ' + labelOf(card.dataset.time);
            }
        }

        function reopenCard(card) {
            if (!card) return;
            card.classList.remove('is-finished');
            card.classList.add('is-active');
            var body = card.querySelector('[data-card-body]');
            if (body) body.classList.remove('d-none');
            var finished = card.querySelector('[data-card-finished]');
            if (finished) finished.classList.add('d-none');
            activeCard = card;
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function refresh() {
            var cards = getCards();
            if (actionBar) actionBar.classList.toggle('d-none', !(cards.length === 1 && cards[0].classList.contains('is-finished')));
            if (continueBtn) {
                continueBtn.disabled = cards.length === 0 || cards.some(function (card) { return !card.dataset.time; });
            }
        }

        function addHidden(name, value) {
            if (value === null || value === undefined || value === '') return;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            input.setAttribute('data-generated', '');
            submitForm.appendChild(input);
        }

        function submitList() {
            submitForm.querySelectorAll('[data-generated]').forEach(function (input) { input.remove(); });
            var cards = getCards();
            if (!cards.length) return;

            var first = cards[0];
            addHidden('service', first.dataset.serviceId);
            addHidden('date', bookingDate);
            addHidden('time', first.dataset.time);

            if (first === mainCard && first.dataset.multistaff === '1') {
                mainCard.querySelectorAll('input[name^="staff["]:checked').forEach(function (radio) {
                    addHidden(radio.name, radio.value);
                });
            } else {
                var mainPersonnel = personnelValue(first);
                addHidden('personnel', mainPersonnel === '' ? 'any' : mainPersonnel);
            }

            if (first.dataset.variantId) addHidden('variant_id', first.dataset.variantId);

            for (var i = 1; i < cards.length; i++) {
                var card = cards[i];
                var index = i - 1;
                addHidden('extras[' + index + '][service_id]', card.dataset.serviceId);
                if (card.dataset.variantLabel) addHidden('extras[' + index + '][variant_label]', card.dataset.variantLabel);
                var personnel = personnelValue(card);
                if (personnel !== '' && personnel !== 'any') addHidden('extras[' + index + '][personnel_id]', personnel);
                addHidden('extras[' + index + '][start_time]', card.dataset.time);
            }

            submitForm.submit();
        }

        list.addEventListener('click', function (event) {
            var slot = event.target.closest('[data-slot-time]');
            if (slot) {
                var card = slot.closest('[data-card]');
                if (!card || card.classList.contains('is-finished')) return;
                setSelectedTime(card, slot.dataset.slotTime);
                finishCard(card);
                activeCard = null;
                refresh();
                return;
            }

            var finished = event.target.closest('[data-card-finished]');
            if (finished) {
                reopenCard(finished.closest('[data-card]'));
                refresh();
            }
        });

        continueBtn.addEventListener('click', submitList);

        var mainTime = mainCard.dataset.time;
        if (mainTime) {
            setSelectedTime(mainCard, mainTime);
        }

        refresh();
    })();
</script>
@endpush
