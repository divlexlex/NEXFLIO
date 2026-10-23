{{-- Branch Booking — Step 3: Date, Personnel & Time (Phase 3A).

     The schedule step now builds a LIST of services on one date: card #1 is
     the main service (server-rendered personnel/time, unchanged rules), and
     every added service is a card of its own with its own personnel and start
     time. Availability is still computed server-side; the added cards only
     make selection smooth (no reloads while building the list). store()
     re-validates every segment authoritatively. --}}
@extends('layouts.public')

@section('title', 'Book — Date & Time')

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
                            <p class="nx-eyebrow mb-1">Branch Booking</p>
                            <h1 class="h5 mb-0">Choose Date &amp; Time</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 2])
                    </div>

                    {{-- DATE --}}
                    <form method="GET" action="{{ route('account.booking.branch.schedule') }}" id="dateForm">
                        <input type="hidden" name="service" value="{{ $service->id }}">
                        @if($variant)<input type="hidden" name="variant" value="{{ $variant->id }}">@endif
                        @include('partials.booking.carry-extras', ['extras' => $extras])
                        <label class="form-label nx-eyebrow">Date</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="date" name="date" value="{{ $date }}" min="{{ $minDate }}" max="{{ $maxDate }}"
                                   class="form-control" style="max-width: 220px;" onchange="document.getElementById('dateForm').submit()" required>
                            <button type="submit" class="nx-btn nx-btn-outline">Update</button>
                        </div>
                    </form>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    <div id="scheduleList" class="nx-svc-list">
                        {{-- CARD #1 — MAIN SERVICE (server-rendered, unchanged) --}}
                        <div class="nx-svc-card is-active" id="svcCardMain" data-card
                             data-service-id="{{ $service->id }}"
                             data-name="{{ $service->name }}"
                             data-category="{{ $service->category }}"
                             data-duration="{{ (int) $service->duration_minutes }}"
                             data-price="{{ (float) ($variant?->price ?? $service->price) }}"
                             data-downpayment="{{ $service->down_payment !== null ? (float) $service->down_payment : '' }}"
                             data-variant-id="{{ $variant?->id }}"
                             data-variant-label="{{ $variant?->label }}"
                             data-multistaff="{{ $isMultiStaff ? '1' : '0' }}"
                             data-time="{{ $selectedTime ?? '' }}">
                            <div class="nx-svc-card-head">
                                <div>
                                    <div class="nx-svc-card-name">{{ $service->name }}@if($variant) <span class="nx-text-secondary">— {{ $variant->label }}</span>@endif</div>
                                    <div class="nx-svc-card-meta">{{ $service->duration_minutes }} mins &middot; ₱{{ number_format($variant?->price ?? $service->price, 2) }}</div>
                                    <div class="nx-svc-card-dp">Down payment: {{ $service->down_payment !== null ? '₱'.number_format((float) $service->down_payment, 2) : 'none' }}</div>
                                </div>
                                <button type="button" class="nx-svc-card-discard" data-card-discard aria-label="Discard service">&times;</button>
                            </div>

                            <div class="nx-svc-card-finished d-none" data-card-finished>
                                <span data-finished-summary></span>
                                <span class="nx-svc-card-edit">tap to edit</span>
                            </div>

                            <div class="nx-svc-card-body" data-card-body>
                                <p class="nx-eyebrow mb-2">Preferred Spa Personnel</p>
                                <form method="GET" action="{{ route('account.booking.branch.schedule') }}" id="personnelForm">
                                    <input type="hidden" name="service" value="{{ $service->id }}">
                                    @if($variant)<input type="hidden" name="variant" value="{{ $variant->id }}">@endif
                                    <input type="hidden" name="date" value="{{ $date }}">
                                    @include('partials.booking.carry-extras', ['extras' => $extras])
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

                    {{-- BOTTOM ACTION BAR (2+ services) --}}
                    <div class="nx-svc-actionbar d-none" id="scheduleActionBar">
                        <button type="button" class="nx-btn nx-btn-outline" id="backFirstBtn">&larr; Back to service</button>
                        <div class="d-flex flex-wrap gap-2 ms-auto">
                            <button type="button" class="nx-btn nx-btn-outline" id="discardActiveBtn">Discard</button>
                            <button type="button" class="nx-btn nx-btn-outline" id="addServiceBtn2">+ Add another service</button>
                            <button type="button" class="nx-btn nx-btn-primary" id="scheduleContinueBtn">Continue &rarr;</button>
                        </div>
                    </div>
                </div>

                <a href="{{ route('account.booking.branch.service.show', $service->id) }}" class="nx-btn nx-btn-outline">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>
    </div>

    {{-- SUBMIT FORM — JS maps the built list onto the existing field names. --}}
    <form method="GET" action="{{ route('account.booking.branch.details') }}" id="scheduleSubmitForm" class="d-none"></form>

    {{-- POPUP #1 — after a time is picked on the active card. --}}
    <div class="modal fade" id="addAnotherModal" tabindex="-1" aria-hidden="true" aria-labelledby="addAnotherLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 nx-body" style="border-radius: var(--nx-radius-lg); overflow: hidden;">
                <div class="modal-header" style="border-color: var(--nx-border);">
                    <h2 id="addAnotherLabel" class="h5 mb-0">Add another service?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="addAnotherSummary"></p>
                </div>
                <div class="modal-footer" style="border-color: var(--nx-border);">
                    <button type="button" class="nx-btn nx-btn-outline" id="addAnotherContinue">Not now — Continue</button>
                    <button type="button" class="nx-btn nx-btn-primary" id="addAnotherOpenPicker">+ Add another service</button>
                </div>
            </div>
        </div>
    </div>

    {{-- POPUP #2 — services picker (same cards/pills as the booking modal). --}}
    <div class="modal fade" id="servicePickerModal" tabindex="-1" aria-hidden="true" aria-labelledby="servicePickerLabel">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 nx-body" style="border-radius: var(--nx-radius-lg); overflow: hidden;">
                <div class="modal-header" style="border-color: var(--nx-border);">
                    <div>
                        <p class="nx-eyebrow mb-1">Add another service</p>
                        <h2 id="servicePickerLabel" class="h5 mb-0">Pick a service for the same visit</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="max-height: 75vh; overflow-y: auto;">
                    <ul class="nav nav-pills justify-content-center mb-4 gap-2 flex-wrap" id="pickerPills" role="tablist"></ul>
                    <div class="row g-3" id="pickerServices"></div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('styles')
<style>
    .nx-svc-list { display: flex; flex-direction: column; gap: 16px; }
    .nx-svc-card { border: 1px solid var(--nx-border); border-radius: var(--nx-radius-lg); padding: 18px; background: var(--nx-surface, #fff); transition: box-shadow .2s ease, border-color .2s ease, background .2s ease, transform .2s ease; }
    .nx-svc-card.is-active { border-color: var(--nx-accent); box-shadow: 0 0 0 1px var(--nx-accent); }
    .nx-svc-card.is-finished { padding: 12px 16px; background: rgba(0,0,0,.025); }
    .nx-svc-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .nx-svc-card-name { font-weight: 600; }
    .nx-svc-card-meta { font-size: 13px; color: var(--nx-text-secondary); }
    .nx-svc-card-dp { font-size: 12px; color: var(--nx-text-secondary); }
    .nx-svc-card-discard { background: none; border: 0; color: var(--nx-text-secondary); font-size: 20px; line-height: 1; }
    .nx-svc-card-discard:hover { color: var(--nx-danger, #c0392b); }
    .nx-svc-card-finished { display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 14px; cursor: pointer; }
    .nx-svc-card-edit { font-size: 12px; color: var(--nx-accent); }
    .nx-svc-card-body { margin-top: 14px; }
    .nx-svc-personnel { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 4px; }
    .nx-svc-personnel .nx-select-card { padding: 8px 12px; }
    .nx-slot-btn { transition: background .18s ease, color .18s ease, border-color .18s ease, transform .12s ease; }
    .nx-slot-btn.is-selected { transform: translateY(-1px); }
    .nx-svc-actionbar { position: sticky; bottom: 12px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 18px; padding: 12px; border: 1px solid var(--nx-border); border-radius: var(--nx-radius-lg); background: var(--nx-surface, #fff); box-shadow: 0 6px 18px rgba(0,0,0,.08); }
</style>
@endpush

@push('scripts')
@php
    $initialExtras = collect($extras)->map(fn ($extra) => array_filter([
        'service_id' => $extra['service']->id,
        'variant_label' => $extra['variant_label'],
        'personnel_id' => $extra['personnel_id'],
        'start_time' => $extra['start_time'],
    ], fn ($value) => $value !== null && $value !== ''))->values();
@endphp
<script>
    (function () {
        var list = document.getElementById('scheduleList');
        var mainCard = document.getElementById('svcCardMain');
        var submitForm = document.getElementById('scheduleSubmitForm');
        var actionBar = document.getElementById('scheduleActionBar');
        var continueBtn = document.getElementById('scheduleContinueBtn');
        var backFirstBtn = document.getElementById('backFirstBtn');
        var discardActiveBtn = document.getElementById('discardActiveBtn');
        var addServiceBtn2 = document.getElementById('addServiceBtn2');
        if (!list || !mainCard || !submitForm) return;

        var catalog = @json($serviceCatalog);
        var slotOptions = @json($slotOptions);
        var closeMinute = @json($closeMinute);
        var initialExtras = @json($initialExtras);
        var bookingDate = @json($date);
        var uid = 0;
        var activeCard = mainCard;
        var pickerBuilt = false;

        var addAnotherModal = new bootstrap.Modal(document.getElementById('addAnotherModal'));
        var pickerModal = new bootstrap.Modal(document.getElementById('servicePickerModal'));

        function serviceData(id) { return catalog[String(id)] || null; }
        function money(value) { return '₱' + Number(value || 0).toFixed(2); }
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

        function discardCard(card) {
            if (!card) return;
            var wasActive = card === activeCard;
            card.remove();
            var remaining = getCards();
            if (remaining.length === 0) {
                activeCard = null;
            } else if (wasActive) {
                activeCard = null;
                reopenCard(remaining[remaining.length - 1]);
            }
            refresh();
        }

        function refresh() {
            var cards = getCards();
            if (actionBar) actionBar.classList.toggle('d-none', cards.length < 2);
            if (backFirstBtn && cards.length) {
                backFirstBtn.textContent = '← Back to ' + cards[0].dataset.name;
            }
            if (continueBtn) {
                continueBtn.disabled = cards.length === 0 || cards.some(function (card) { return !card.dataset.time; });
            }
            if (discardActiveBtn) discardActiveBtn.disabled = !activeCard;
        }

        function optionPill(category, first) {
            var li = document.createElement('li');
            li.className = 'nav-item';
            li.setAttribute('role', 'presentation');
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'nav-link nx-pill' + (first ? ' active' : '');
            button.textContent = category;
            button.addEventListener('click', function () {
                document.querySelectorAll('#pickerPills .nx-pill').forEach(function (pill) { pill.classList.remove('active'); });
                button.classList.add('active');
                document.querySelectorAll('#pickerServices [data-picker-category]').forEach(function (group) {
                    group.classList.toggle('d-none', group.dataset.pickerCategory !== category);
                });
            });
            li.appendChild(button);
            return li;
        }

        function buildPicker() {
            if (pickerBuilt) return;
            pickerBuilt = true;
            var pills = document.getElementById('pickerPills');
            var grid = document.getElementById('pickerServices');
            var groups = {};
            Object.keys(catalog).forEach(function (id) {
                var service = catalog[id];
                var category = service.category || 'Services';
                (groups[category] = groups[category] || []).push(service);
            });
            var first = true;
            Object.keys(groups).forEach(function (category) {
                pills.appendChild(optionPill(category, first));
                var wrap = document.createElement('div');
                wrap.className = 'col-12' + (first ? '' : ' d-none');
                wrap.dataset.pickerCategory = category;
                var row = document.createElement('div');
                row.className = 'row g-3';
                groups[category].forEach(function (service) {
                    var col = document.createElement('div');
                    col.className = 'col-6 col-md-4';
                    var card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'nx-card d-block h-100 text-start w-100 border-0 p-0';
                    card.innerHTML =
                        '<div class="nx-card-media" style="height:110px;">' +
                            (service.image_url
                                ? '<img src="' + service.image_url + '" alt="">'
                                : '<i class="bi bi-image fs-3 opacity-50"></i>') +
                        '</div>' +
                        '<div class="nx-card-body" style="padding:14px 16px 16px;">' +
                            '<h3 class="nx-card-title h6 mb-1" style="font-size:15px;">' + escapeHtml(service.name) + '</h3>' +
                            '<p class="small nx-text-secondary mb-2">' + service.duration_minutes + ' mins</p>' +
                            '<span class="nx-card-price">' + money(service.price) + '</span>' +
                        '</div>';
                    card.addEventListener('click', function () {
                        addServiceCard(service);
                        pickerModal.hide();
                    });
                    col.appendChild(card);
                    row.appendChild(col);
                });
                wrap.appendChild(row);
                grid.appendChild(wrap);
                first = false;
            });
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function createCard(service) {
            var id = service.id;
            var card = document.createElement('div');
            card.className = 'nx-svc-card';
            card.setAttribute('data-card', '');
            card.dataset.serviceId = id;
            card.dataset.name = service.name;
            card.dataset.category = service.category || '';
            card.dataset.duration = service.duration_minutes;
            card.dataset.price = service.price;
            card.dataset.downpayment = service.down_payment == null ? '' : service.down_payment;
            card.dataset.variantId = '';
            card.dataset.variantLabel = '';
            card.dataset.multistaff = '0';
            card.dataset.time = '';

            var variants = service.variants || [];
            var variantHtml = '';
            if (variants.length) {
                variantHtml =
                    '<div class="mb-3">' +
                        '<label class="form-label small mb-1">Variant</label>' +
                        '<select class="form-select" data-card-variant>' +
                            variants.map(function (variant) {
                                return '<option value="' + escapeHtml(variant.label) + '" data-id="' + variant.id + '">' +
                                    escapeHtml(variant.label) + ' — ' + money(variant.price) + '</option>';
                            }).join('') +
                        '</select>' +
                    '</div>';
            }

            var personnel = service.personnel || [];
            var personnelHtml =
                '<div class="mb-3">' +
                    '<label class="form-label small mb-1">Preferred Spa Personnel</label>' +
                    '<div class="nx-svc-personnel">' +
                        '<label class="nx-select-card d-flex align-items-center gap-2">' +
                            '<input type="radio" name="svc_personnel_' + (++uid) + '" value="" checked data-card-personnel data-personnel-name="No preferred personnel">' +
                            '<span>No preferred personnel</span>' +
                        '</label>' +
                        personnel.map(function (member, index) {
                            return '<label class="nx-select-card d-flex align-items-center gap-2">' +
                                '<input type="radio" name="svc_personnel_' + uid + '" value="' + member.id + '" data-card-personnel data-personnel-name="' + escapeHtml(member.name) + '">' +
                                '<span>' + escapeHtml(member.name) + '</span>' +
                            '</label>';
                        }).join('') +
                    '</div>' +
                '</div>';

            var slotsHtml = slotOptions.filter(function (option) {
                if (closeMinute == null) return true;
                return option.minute + Number(service.duration_minutes || 30) <= closeMinute;
            }).map(function (option) {
                return '<button type="button" class="nx-slot-btn" data-slot-time="' + option.time + '">' + option.label + '</button>';
            }).join('');

            card.innerHTML =
                '<div class="nx-svc-card-head">' +
                    '<div>' +
                        '<div class="nx-svc-card-name">' + escapeHtml(service.name) + '</div>' +
                        '<div class="nx-svc-card-meta">' + service.duration_minutes + ' mins · ' + money(service.price) + '</div>' +
                        '<div class="nx-svc-card-dp">Down payment: ' + (service.down_payment == null ? 'none' : money(service.down_payment)) + '</div>' +
                    '</div>' +
                    '<button type="button" class="nx-svc-card-discard" data-card-discard aria-label="Discard service">&times;</button>' +
                '</div>' +
                '<div class="nx-svc-card-finished d-none" data-card-finished>' +
                    '<span data-finished-summary></span><span class="nx-svc-card-edit">tap to edit</span>' +
                '</div>' +
                '<div class="nx-svc-card-body" data-card-body>' +
                    variantHtml + personnelHtml +
                    '<label class="form-label small mb-1">Time</label>' +
                    '<div class="nx-slot-grid">' + slotsHtml + '</div>' +
                '</div>';

            var variantSelect = card.querySelector('[data-card-variant]');
            if (variantSelect) {
                function syncVariant() {
                    var option = variantSelect.options[variantSelect.selectedIndex];
                    card.dataset.variantLabel = variantSelect.value;
                    card.dataset.variantId = option ? option.dataset.id : '';
                }
                variantSelect.addEventListener('change', syncVariant);
                syncVariant();
            }

            return card;
        }

        function addServiceCard(service) {
            if (activeCard && activeCard.dataset.time) finishCard(activeCard);
            var card = createCard(service);
            list.appendChild(card);
            reopenCard(card);
            refresh();
        }

        function addServiceFromExtra(row) {
            var service = serviceData(row.service_id);
            if (!service) return null;
            var card = createCard(service);

            if (row.variant_label) {
                var select = card.querySelector('[data-card-variant]');
                if (select) {
                    select.value = row.variant_label;
                    select.dispatchEvent(new Event('change'));
                }
            }

            if (row.personnel_id) {
                var radio = card.querySelector('[data-card-personnel][value="' + row.personnel_id + '"]');
                if (radio) radio.checked = true;
            }

            list.appendChild(card);
            if (row.start_time) setSelectedTime(card, row.start_time);

            return card;
        }

        function showAddAnother(card) {
            activeCard = card;
            var summary = document.getElementById('addAnotherSummary');
            if (summary) {
                summary.textContent = card.dataset.name + ' with ' + personnelLabel(card) + ' at ' + labelOf(card.dataset.time) + '.';
            }
            addAnotherModal.show();
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
                showAddAnother(card);
                return;
            }

            var discard = event.target.closest('[data-card-discard]');
            if (discard) {
                discardCard(discard.closest('[data-card]'));
                return;
            }

            var finished = event.target.closest('[data-card-finished]');
            if (finished) {
                reopenCard(finished.closest('[data-card]'));
                refresh();
            }
        });

        document.getElementById('addAnotherContinue').addEventListener('click', function () {
            addAnotherModal.hide();
            submitList();
        });

        document.getElementById('addAnotherOpenPicker').addEventListener('click', function () {
            addAnotherModal.hide();
            buildPicker();
            pickerModal.show();
        });

        addServiceBtn2.addEventListener('click', function () {
            buildPicker();
            pickerModal.show();
        });

        backFirstBtn.addEventListener('click', function () {
            var cards = getCards();
            if (!cards.length) return;
            cards.forEach(function (card) {
                if (card !== cards[0]) finishCard(card);
            });
            reopenCard(cards[0]);
            refresh();
        });

        discardActiveBtn.addEventListener('click', function () {
            discardCard(activeCard);
        });

        continueBtn.addEventListener('click', submitList);

        // Rebuild the submitted list on a validation bounce.
        var mainTime = mainCard.dataset.time;
        if (initialExtras.length) {
            if (mainTime) finishCard(mainCard);
            var last = null;
            initialExtras.forEach(function (row) {
                last = addServiceFromExtra(row) || last;
            });
            if (last) reopenCard(last);
        } else if (mainTime) {
            setSelectedTime(mainCard, mainTime);
        }

        refresh();
    })();
</script>
@endpush
