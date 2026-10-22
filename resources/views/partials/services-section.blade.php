{{-- Shared "Our Services" catalog section — DB-driven category sidebar (see
     App\Support\ServiceCatalog). Included on both Guest Home (#services) and
     the dedicated /services page so the two stay in sync.
     Expects: $servicesByCategory — categories of real Service arrays.
     Optional: $activeCategory — pre-selects a sidebar item (see
     ServicesController's ?category= handling, used by the Home page's
     category rail); defaults to "All Services". --}}
<section id="services" class="nx-section" style="background: var(--nx-bg-page);">
    <div class="container">
        <div class="text-center mb-4">
            <p class="nx-eyebrow mb-2">Full Menu</p>
            <h2 class="nx-section-title">Our Services</h2>
            <p class="nx-text-secondary mt-2 mb-0">Whole-hearted care, per session pricing. Tap any service for details.</p>
        </div>

        @php($activeCategory = $activeCategory ?? null)
        @if($servicesByCategory->isEmpty())
            <p class="text-center nx-text-secondary">Our service menu is being polished — check back soon.</p>
        @else
            @php($allServices = $servicesByCategory->flatten(1)->sortBy('name')->values())
            @php($packageServices = $servicesByCategory->flatten(1)->whereIn('name', \App\Support\ServiceCatalog::PACKAGE_SERVICE_NAMES)->sortBy('name')->values())
            <div class="nx-search mb-4 mx-auto" style="max-width: 420px;">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                    <input type="search" id="serviceSearch" class="form-control border-start-0"
                           placeholder="Search services..." aria-label="Search services within the selected category">
                </div>
            </div>

            <div class="nx-services-layout">
                <div class="nx-services-cat-dropdown d-lg-none mb-3">
                    <label class="nx-eyebrow mb-2">Category</label>
                    <div class="dropdown">
                        <button class="nx-cat-btn dropdown-toggle" type="button" id="serviceCategoryDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span id="serviceCategoryLabel">{{ $activeCategory ?? 'All Services' }}</span>
                        </button>
                        <ul class="dropdown-menu nx-cat-menu" aria-labelledby="serviceCategoryDropdown">
                            <li><button type="button" class="dropdown-item {{ !$activeCategory ? 'active' : '' }}" data-cat-target="#cat-all" data-cat-label="All Services">All Services</button></li>
                            <li><button type="button" class="dropdown-item {{ $activeCategory === 'Packages' ? 'active' : '' }}" data-cat-target="#cat-packages" data-cat-label="Packages">Packages</button></li>
                            @foreach($servicesByCategory as $catName => $catServices)
                                <li><button type="button" class="dropdown-item {{ $activeCategory === $catName ? 'active' : '' }}" data-cat-target="#cat-{{ $loop->index }}" data-cat-label="{{ $catName }}">{{ $catName }}</button></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="nx-services-sidebar nav" role="tablist" aria-orientation="vertical">
                    <button class="nav-link {{ $activeCategory ? '' : 'active' }}"
                            data-bs-toggle="pill" data-bs-target="#cat-all" type="button" role="tab">
                        <i class="bi bi-grid"></i>All Services
                    </button>
                    {{-- Fixed "Packages" tab presenting the Head Spa package
                         records (ServiceCatalog::PACKAGE_SERVICE_NAMES). This
                         is a VIEW-level grouping, distinct from a service
                         category literally named "Packages": if a service is
                         assigned to that DB category, its own pill/section
                         also renders below (same label, different contents).
                         Both are kept working on purpose — the fixed tab
                         always presents the Head Spa packages; the DB tab
                         presents that category's services. --}}
                    <button class="nav-link {{ $activeCategory === 'Packages' ? 'active' : '' }}"
                            data-bs-toggle="pill" data-bs-target="#cat-packages" type="button" role="tab">
                        <i class="bi bi-gift"></i>Packages
                    </button>
                    @foreach($servicesByCategory as $category => $services)
                        <button class="nav-link {{ $activeCategory === $category ? 'active' : '' }}"
                                data-bs-toggle="pill" data-bs-target="#cat-{{ $loop->index }}" type="button" role="tab">
                            <i class="bi {{ \App\Support\ServiceCatalog::CATEGORY_ICONS[$category] ?? 'bi-tag' }}"></i>{{ $category }}
                        </button>
                    @endforeach
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade {{ $activeCategory ? '' : 'show active' }}" id="cat-all" role="tabpanel">
                        <div class="row g-3">
                            @foreach($allServices as $service)
                                <div class="col-md-6 col-lg-4 d-flex flex-column" data-service-name="{{ strtolower($service['name']) }}">
                                    @include('partials.item-card', [
                                        'url' => route('catalog.service', $service['id']),
                                        'imageUrl' => $service['image_url'],
                                        'title' => $service['name'],
                                        'price' => $service['price'],
                                        'subtitle' => $service['duration_minutes'] . ' mins',
                                        'badge' => $service['badge'],
                                        'ctaText' => 'Book',
                                    ])
                                </div>
                            @endforeach
                        </div>
                        <p class="nx-search-empty d-none text-center nx-text-secondary mt-4 mb-0">No services match your search.</p>
                    </div>
                    <div class="tab-pane fade {{ $activeCategory === 'Packages' ? 'show active' : '' }}" id="cat-packages" role="tabpanel">
                        <div class="row g-3">
                            @foreach($packageServices as $service)
                                <div class="col-md-6 col-lg-4 d-flex flex-column" data-service-name="{{ strtolower($service['name']) }}">
                                    @include('partials.item-card', [
                                        'url' => route('catalog.service', $service['id']),
                                        'imageUrl' => $service['image_url'],
                                        'title' => $service['name'],
                                        'price' => $service['price'],
                                        'subtitle' => $service['duration_minutes'] . ' mins',
                                        'badge' => $service['badge'],
                                        'ctaText' => 'Book',
                                    ])
                                </div>
                            @endforeach
                        </div>
                        <p class="nx-search-empty d-none text-center nx-text-secondary mt-4 mb-0">No services match your search.</p>
                        <p class="mt-4 mb-0">
                            <a href="{{ route('packages') }}" class="nx-text-accent fw-semibold">See package tiers &amp; add-ons -&gt;</a>
                        </p>
                    </div>
                    @foreach($servicesByCategory as $category => $services)
                        <div class="tab-pane fade {{ $activeCategory === $category ? 'show active' : '' }}" id="cat-{{ $loop->index }}" role="tabpanel">
                            <div class="row g-3">
                                @foreach($services as $service)
                                    <div class="col-md-6 col-lg-4 d-flex flex-column" data-service-name="{{ strtolower($service['name']) }}">
                                        @include('partials.item-card', [
                                            'url' => route('catalog.service', $service['id']),
                                            'imageUrl' => $service['image_url'],
                                            'title' => $service['name'],
                                            'price' => $service['price'],
                                            'subtitle' => $service['duration_minutes'] . ' mins',
                                            'badge' => $service['badge'],
                                            'ctaText' => 'Book',
                                        ])
                                    </div>
                                @endforeach
                            </div>
                            <p class="nx-search-empty d-none text-center nx-text-secondary mt-4 mb-0">No services match your search.</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>

@once
    @push('scripts')
        <script>
            // Lightweight client-side search over whichever category tab is active —
            // filters the cards already on the page, no new backend/route involved.
            (function () {
                var input = document.getElementById('serviceSearch');
                if (!input) return;

                function applyFilter() {
                    var term = input.value.trim().toLowerCase();
                    document.querySelectorAll('#services .tab-pane').forEach(function (pane) {
                        var anyVisible = false;
                        pane.querySelectorAll('[data-service-name]').forEach(function (card) {
                            var match = !term || card.dataset.serviceName.indexOf(term) !== -1;
                            card.style.display = match ? '' : 'none';
                            if (match) anyVisible = true;
                        });
                        var empty = pane.querySelector('.nx-search-empty');
                        if (empty) empty.classList.toggle('d-none', anyVisible);
                    });
                }

                input.addEventListener('input', applyFilter);
            })();
        </script>
    @endpush
@endonce

@push('scripts')
    <script>
        (function () {
            var menu = document.querySelector('.nx-cat-menu');
            if (!menu) return;
            var label = document.getElementById('serviceCategoryLabel');
            menu.addEventListener('click', function (e) {
                var item = e.target.closest('.nx-cat-menu .dropdown-item');
                if (!item) return;
                var btn = document.querySelector('[data-bs-target="' + item.dataset.catTarget + '"]');
                if (btn) btn.click();
                if (label) label.textContent = item.dataset.catLabel;
                menu.querySelectorAll('.dropdown-item').forEach(function (el) { el.classList.remove('active'); });
                item.classList.add('active');
            });
        })();
    </script>
@endpush
