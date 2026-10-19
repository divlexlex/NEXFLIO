{{-- Branch Booking — Step 2: Service details (Phase 3A). Real Service
     record only (BookingController@serviceShow 404s for missing/inactive
     ids). No detail here is invented — name/description/price/duration/
     category all come straight from the services table.

     Problem #4: the same visit can carry EXTRA services. The main service
     keeps every existing behaviour (packages with variants included); an
     extra is always a regular, non-package branch service. Rows are only
     collected here — store() re-resolves every price and validates every
     service/personnel choice server-side. --}}
@extends('layouts.public')

@section('title', 'Book — ' . $service->name)

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="nx-booking-panel">
                    <div class="nx-booking-panel-header">
                        <div>
                            <p class="nx-eyebrow mb-1">Branch Booking</p>
                            <h1 class="h5 mb-0">Service Details</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 1])
                    </div>

                    <div class="row g-4 align-items-center">
                        <div class="col-md-5">
                            <div class="nx-card-media" style="height: 220px; border-radius: var(--nx-radius-lg);">
                                @if($service->image_url)
                                    <img src="{{ $service->image_url }}" alt="{{ $service->name }}">
                                @else
                                    <i class="bi bi-image fs-1 opacity-50"></i>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-7">
                            <span class="nx-badge mb-2">{{ $service->category }}</span>
                            <h2 class="h4 mb-2">{{ $service->name }}</h2>
                            <p class="h5 nx-text-accent mb-3">₱{{ number_format($service->price, 2) }}</p>
                            <p class="nx-text-secondary mb-2"><i class="bi bi-clock me-1"></i>{{ $service->duration_minutes }} minutes</p>
                            <p class="nx-text-secondary mb-2">Down payment: {{ $service->down_payment !== null ? '₱'.number_format((float) $service->down_payment, 2) : 'none' }}</p>
                            @if($service->description)
                                <p class="mb-0">{{ $service->description }}</p>
                            @endif
                        </div>
                    </div>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    <form method="GET" action="{{ route('account.booking.branch.schedule') }}" id="serviceForm">
                        <input type="hidden" name="service" value="{{ $service->id }}">

                        @if($service->variants->isNotEmpty())
                            {{-- Package service: the client must pick a tier; its
                                 price (not the base services.price) is what gets
                                 charged. Continue carries the chosen variant id. --}}
                            <label class="form-label nx-eyebrow mb-2">Package Option</label>
                            <div class="row g-2 mb-4">
                                @foreach($service->variants as $option)
                                    <div class="col-sm-4">
                                        {{-- No hardcoded is-selected here: the stylesheet highlights
                                             the chosen card via .nx-select-card:has(input:checked),
                                             and the first radio stays checked so a tier is
                                             preselected (and highlighted) on load. --}}
                                        <label class="nx-select-card d-flex flex-column gap-1">
                                            <input type="radio" name="variant" value="{{ $option->id }}" {{ $loop->first ? 'checked' : '' }}>
                                            <span class="fw-semibold">{{ $option->label }}</span>
                                            <small class="nx-text-secondary">₱{{ number_format($option->price, 2) }}@if($option->guestLabel()) &middot; {{ $option->guestLabel() }}@endif</small>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- EXTRA SERVICES — same date, same time, same appointment. --}}
                        @if($extraServices->isNotEmpty())
                            <div class="mb-4">
                                <label class="form-label nx-eyebrow mb-2">Avail Services <span class="nx-text-secondary" style="font-weight:400;">(add 1 or more service)</span></label>
                                <p class="small nx-text-secondary mb-2">Add more services to this same visit</p>

                                <div id="extra-rows"></div>

                                <button type="button" class="nx-btn nx-btn-outline btn-sm" id="extra-add">
                                    <i class="bi bi-plus-lg me-1"></i>Add another service
                                </button>
                            </div>
                        @endif

                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <a href="{{ route('account.booking.branch.service') }}" class="nx-btn nx-btn-outline"
                               data-bs-toggle="modal" data-bs-target="#bookServiceModal">
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

@push('scripts')
<script>
    (function () {
        var addButton = document.getElementById('extra-add');
        var rows = document.getElementById('extra-rows');
        if (!addButton || !rows) return;

        // Server-provided catalogs: extras are active non-package branch
        // services; staff are active, non-break members with their position.
        var services = @json($extraServices);
        var staff = @json($staffCatalog);
        var nextIndex = 0;

        function option(value, label, selected) {
            var el = document.createElement('option');
            el.value = value;
            el.textContent = label;
            if (selected) el.selected = true;
            return el;
        }

        function refreshPersonnel(row) {
            var serviceSelect = row.querySelector('[data-extra-service]');
            var personnel = row.querySelector('[data-extra-personnel]');
            var service = services.find(function (item) { return String(item.id) === serviceSelect.value; });
            personnel.innerHTML = '';
            personnel.appendChild(option('', 'No preference'));

            if (!service || !service.positions || service.positions.length === 0) {
                return;
            }

            staff.forEach(function (member) {
                if (service.positions.indexOf(member.position) !== -1) {
                    personnel.appendChild(option(String(member.id), member.name));
                }
            });
        }

        function refreshVariants(row) {
            var serviceSelect = row.querySelector('[data-extra-service]');
            var wrap = row.querySelector('[data-extra-variant-wrap]');
            var variant = row.querySelector('[data-extra-variant]');
            var service = services.find(function (item) { return String(item.id) === serviceSelect.value; });

            variant.innerHTML = '';
            var hasVariants = service && service.variants && service.variants.length > 0;
            wrap.classList.toggle('d-none', !hasVariants);
            variant.required = !!hasVariants;
            if (!hasVariants) return;

            service.variants.forEach(function (item) {
                variant.appendChild(option(item.label, item.label + ' — ₱' + Number(item.price).toFixed(2)));
            });
        }

        function addRow() {
            var index = nextIndex++;
            var row = document.createElement('div');
            row.className = 'border rounded p-3 mb-2 position-relative';
            row.setAttribute('data-extra-row', '');
            row.innerHTML =
                '<div class="row g-2 align-items-end" style="position:relative;">' +
                    '<div class="col-md-5">' +
                        '<label class="form-label small mb-1">Service</label>' +
                        '<select class="form-select" data-extra-service name="extras[' + index + '][service_id]"></select>' +
                    '</div>' +
                    '<div class="col-md-3 d-none" data-extra-variant-wrap>' +
                        '<label class="form-label small mb-1">Variant</label>' +
                        '<select class="form-select" data-extra-variant name="extras[' + index + '][variant_label]"></select>' +
                    '</div>' +
                    '<div class="col-md-3">' +
                        '<label class="form-label small mb-1">Preferred personnel</label>' +
                        '<select class="form-select" data-extra-personnel name="extras[' + index + '][personnel_id]"></select>' +
                    '</div>' +
                    '<div class="col-12"><p class="small mb-0 mt-1" data-extra-dp-hint></p></div>' +
                '</div>' +
                '<div style="position:absolute; top:8px; right:8px; z-index:5;">' +
                    '<button type="button" class="btn btn-outline-danger btn-sm" data-extra-remove aria-label="Remove service">&times;</button>' +
                '</div>';

            var serviceSelect = row.querySelector('[data-extra-service]');
            serviceSelect.appendChild(option('', 'Choose a service…'));
            services.forEach(function (item) {
                var opt = option(String(item.id), item.name + ' — ₱' + Number(item.price).toFixed(2));
                opt.dataset.dp = item.down_payment == null ? '' : String(item.down_payment);
                serviceSelect.appendChild(opt);
            });

            serviceSelect.addEventListener('change', function () {
                refreshVariants(row);
                refreshPersonnel(row);

                var hint = row.querySelector('[data-extra-dp-hint]');
                var selected = serviceSelect.options[serviceSelect.selectedIndex];
                var dp = selected && selected.dataset.dp !== '' ? Number(selected.dataset.dp) : null;
                hint.textContent = serviceSelect.value === ''
                    ? ''
                    : 'Down payment: ' + (dp !== null && dp > 0 ? '₱' + dp.toFixed(2) : 'none');
            });
            row.querySelector('[data-extra-remove]').addEventListener('click', function () {
                row.remove();
            });

            rows.appendChild(row);
            refreshVariants(row);
            refreshPersonnel(row);
            serviceSelect.focus();
        }

        addButton.addEventListener('click', addRow);
    })();
</script>
@endpush
