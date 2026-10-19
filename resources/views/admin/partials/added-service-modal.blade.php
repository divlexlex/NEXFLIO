{{--
    Shared "Add a service" modal.

    Rendered by BOTH the Appointments drawer and the Payments cards. The button
    that opens it carries data-appointment-id (required) and data-personnel-id
    (the appointment's own staff, optional); the show handler reads them and
    posts to the unchanged additional-services endpoints. The including view
    must provide $services (catalog rows) plus $servicesJson and $personnelJson
    (the same shapes the Appointments page already builds).
--}}
<div class="modal fade" id="addedServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="addedServiceForm" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Add a service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Adds another service to this same appointment (same Appointment ID). The price
                    joins the amount collected at the branch — the Balance due updates accordingly.
                </p>
                <div class="mb-2">
                    <label class="form-label">Service</label>
                    <select name="service_id" id="added-service" class="form-select" required>
                        @foreach($services as $service)
                            @if(! $service->requiresMultiStaff())
                                <option value="{{ $service->id }}">
                                    {{ $service->name }} — ₱{{ number_format($service->price, 2) }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="mb-2 d-none" id="added-variant-wrap">
                    <label class="form-label">Variant</label>
                    <select name="variant_label" id="added-variant" class="form-select"></select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Staff <span class="text-muted">(optional)</span></label>
                    {{-- Options filled by JS from the chosen service category. --}}
                    <select name="personnel_id" id="added-personnel" class="form-select"></select>
                    <div class="small text-muted mt-1 d-none" id="added-personnel-hint"></div>
                </div>

                {{-- Optional down payment collected now. Pre-filled from the
                     chosen service's configured amount (capped at its price);
                     blank/0 records no payment at all. The balance still
                     grows by the FULL added price either way. --}}
                <div class="border-top mt-3 pt-3">
                    <div class="mb-2">
                        <label class="form-label" for="added-dp-input">Down payment (₱) <span class="text-muted">(optional)</span></label>
                        <input type="number" step="0.01" min="0" name="down_payment" id="added-dp-input" class="form-control" placeholder="Leave blank for none">
                        <div class="small text-muted mt-1">Pre-filled from the service's configured amount. Leave blank to collect nothing now; the full added price still joins the Balance due.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="added-dp-method">Method</label>
                        <select name="method" id="added-dp-method" class="form-select">
                            @foreach(config('payment.methods', []) as $value => $label)
                                <option value="{{ $value }}" @selected($value === 'cash')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Add service</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Shared add-service modal behaviour. Self-contained (owns its catalogs and
    // reads the target from the opening button's data attributes) so the
    // Appointments drawer and the Payments cards share one implementation.
    (function () {
        const modal = document.getElementById('addedServiceModal');
        const form = document.getElementById('addedServiceForm');
        const serviceSelect = document.getElementById('added-service');
        const variantWrap = document.getElementById('added-variant-wrap');
        const variantSelect = document.getElementById('added-variant');
        const personnelSelect = document.getElementById('added-personnel');
        const personnelHint = document.getElementById('added-personnel-hint');
        const dpInput = document.getElementById('added-dp-input');
        if (!modal || !form || !serviceSelect || !variantWrap || !variantSelect || !personnelSelect) return;

        const statusBase = @json(url('admin/appointments'));
        const servicesCatalog = @json($servicesJson);
        const staffCatalog = @json($personnelJson ?? []);
        let target = { id: null, personnelId: null };

        function esc(s) {
            return (s == null ? '' : String(s)).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
        }

        function currentService() {
            return servicesCatalog.find((s) => String(s.id) === String(serviceSelect.value));
        }

        // Staff who offer positions linked to a service category (Facial →
        // Facial Technician). No linked positions → empty list.
        function staffForEligiblePositions(names) {
            const positions = names || [];
            if (!positions.length) return [];
            return staffCatalog.filter((s) => positions.includes(s.position));
        }

        function syncVariants() {
            const variants = (currentService() || {}).variants || [];
            if (!variants.length) {
                variantWrap.classList.add('d-none');
                variantSelect.innerHTML = '';
                variantSelect.required = false;
                return;
            }
            variantWrap.classList.remove('d-none');
            variantSelect.required = true;
            variantSelect.innerHTML = variants.map((v) =>
                '<option value="' + esc(v.label) + '">' + esc(v.label) +
                ' — ₱' + Number(v.price).toLocaleString(undefined, { minimumFractionDigits: 2 }) + '</option>'
            ).join('');
        }

        function syncPersonnel() {
            const service = currentService();
            const names = (service && service.eligible_positions) || [];

            // No linked positions → nobody is eligible; force the null choice.
            if (!names.length) {
                personnelSelect.innerHTML = '<option value="">No eligible staff</option>';
                personnelSelect.disabled = true;
                personnelSelect.required = false;
                personnelHint.textContent = 'No staff positions are linked to this service category — the service will be added without a specific staff member.';
                personnelHint.classList.remove('d-none');
                return;
            }

            personnelSelect.disabled = false;
            personnelSelect.required = false;
            personnelHint.classList.add('d-none');

            const eligible = staffForEligiblePositions(names);
            const appointmentId = target.personnelId;
            // Offer the appointment's own personnel as the default only when
            // they actually offer this service category.
            const usesAppointmentStaff = appointmentId
                && eligible.some((s) => String(s.id) === String(appointmentId));

            let options = usesAppointmentStaff
                ? '<option value="' + appointmentId + '" selected>Appointment\'s personnel</option>'
                : '';
            options += '<option value="">No specific staff</option>';
            options += eligible.map((s) =>
                '<option value="' + s.id + '">' + esc(s.name) + (s.position ? ' (' + esc(s.position) + ')' : '') + '</option>'
            ).join('');
            personnelSelect.innerHTML = options;
        }

        // Effective price of the current pick: the chosen variant's price when
        // the service has variants, else the service price.
        function currentPrice() {
            const service = currentService();
            if (!service) return 0;
            const variants = service.variants || [];
            if (variants.length) {
                const chosen = variants.find((v) => String(v.label) === String(variantSelect.value));
                return Number(chosen ? chosen.price : service.price) || 0;
            }
            return Number(service.price) || 0;
        }

        // Pre-fill the optional down payment from the pick's configured
        // amount, never above its price. None → blank.
        function syncDownPayment() {
            if (!dpInput) return;
            const service = currentService();
            const price = currentPrice();
            let configured = service && service.down_payment != null ? Number(service.down_payment) : 0;
            configured = Math.min(Math.max(configured, 0), price);
            dpInput.value = configured > 0
                ? configured.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/,/g, '')
                : '';
        }

        function refresh() { syncVariants(); syncPersonnel(); syncDownPayment(); }

        serviceSelect.addEventListener('change', refresh);
        variantSelect.addEventListener('change', syncDownPayment);

        modal.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            target = {
                id: btn ? btn.getAttribute('data-appointment-id') : null,
                personnelId: btn ? btn.getAttribute('data-personnel-id') : null,
            };
            if (!target.id) return;
            form.action = statusBase + '/' + target.id + '/additional-services';
            serviceSelect.selectedIndex = 0;
            refresh();
        });

        refresh();
    })();
</script>
@endpush
