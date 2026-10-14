@extends('layouts.admin')

@section('title', 'Availability')

@section('content')
<h1 class="h3 mb-4">Availability Management</h1>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-hours" type="button">
            <i class="bi bi-clock me-1"></i>Business Hours
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-staff" type="button">
            <i class="bi bi-people me-1"></i>Weekly Roster
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-services" type="button">
            <i class="bi bi-stars me-1"></i>Service Hours
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-blocked" type="button">
            <i class="bi bi-x-octagon me-1"></i>Blocked Slots
        </button>
    </li>
</ul>

<div class="tab-content">
    {{-- ── TAB 1: BUSINESS HOURS ────────────────────────────────────── --}}
    <div class="tab-pane fade show active" id="tab-hours">
        <div class="card p-3">
            <p class="text-muted small mb-3">Set the store's opening and closing times for each day. These apply to all booking slots.</p>
            <form method="POST" action="{{ route('admin.availability.business-hours') }}">
                @csrf
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr><th>Day</th><th>Open</th><th>Close</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @php($days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'])
                            @foreach($days as $i => $day)
                                @php($hours = $businessHours->firstWhere('day_of_week', $i))
                                <tr>
                                    <td class="fw-semibold">{{ $day }}</td>
                                    <td>
                                        <input type="time" name="hours[{{ $i }}][open_at]"
                                               value="{{ $hours?->open_at ? \Carbon\Carbon::parse($hours->open_at)->format('H:i') : '10:00' }}"
                                               class="form-control form-control-sm w-auto"
                                               {{ !$hours?->is_open ? 'disabled' : '' }}>
                                    </td>
                                    <td>
                                        <input type="time" name="hours[{{ $i }}][close_at]"
                                               value="{{ $hours?->close_at ? \Carbon\Carbon::parse($hours->close_at)->format('H:i') : '21:00' }}"
                                               class="form-control form-control-sm w-auto"
                                               {{ !$hours?->is_open ? 'disabled' : '' }}>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                   name="hours[{{ $i }}][is_open]" value="1"
                                                   {{ ($hours?->is_open ?? true) ? 'checked' : '' }}
                                                   onchange="this.closest('tr').querySelectorAll('input[type=time]').forEach(el => el.disabled = !this.checked)">
                                            <label class="form-check-label small">{{ ($hours?->is_open ?? true) ? 'Open' : 'Closed' }}</label>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-spa mt-2">Save Business Hours</button>
            </form>
        </div>
    </div>

    {{-- ── TAB 2: WEEKLY ROSTER ───────────────────────────────────── --}}
    <div class="tab-pane fade" id="tab-staff">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.availability', ['week' => $rosterPrevWeek]) }}#tab-staff">‹ Prev</a>
            <strong>{{ $rosterWeekLabel }}</strong>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.availability', ['week' => $rosterNextWeek]) }}#tab-staff">Next ›</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.availability') }}#tab-staff">This week</a>
            <a class="btn btn-sm btn-outline-secondary ms-auto" href="{{ route('admin.availability', ['week' => $rosterWeekStart, 'copy_from' => $rosterPrevWeek]) }}#tab-staff">Copy previous week</a>
        </div>
        <form method="POST" action="{{ route('admin.availability.weekly-roster') }}">
            @csrf
            <input type="hidden" name="week_start" value="{{ $rosterWeekStart }}">
            @if($errors->has('am_staff') || $errors->has('pm_staff'))
                <div class="alert alert-danger py-2 small">
                    {{ $errors->first('am_staff') }} {{ $errors->first('pm_staff') }}
                </div>
            @endif
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="card p-3 h-100">
                        <h6 class="mb-2">AM — Morning shift</h6>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <input type="time" name="am_start" value="{{ old('am_start', $rosterAmStart) }}" class="form-control form-control-sm w-auto" required>
                            <span class="text-muted small">to</span>
                            <input type="time" name="am_end" value="{{ old('am_end', $rosterAmEnd) }}" class="form-control form-control-sm w-auto" required>
                        </div>
                        @error('am_start')<div class="text-danger small">{{ $message }}</div>@enderror
                        @error('am_end')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="d-flex flex-wrap gap-2" data-chips="am"></div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-btn="am">+ Add staff</button>
                        </div>
                        <div class="d-none" aria-hidden="true">
                            @foreach($staff as $s)
                                <input class="roster-am" type="checkbox" name="am_staff[]" value="{{ $s->id }}" tabindex="-1"
                                    data-staff-id="{{ $s->id }}" data-staff-name="{{ $s->fullName() }}" data-staff-position="{{ $s->staffProfile->position ?? 'Staff' }}"
                                    {{ in_array($s->id, old('am_staff', $rosterAmStaff)) ? 'checked' : '' }}>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card p-3 h-100">
                        <h6 class="mb-2">PM — Afternoon shift</h6>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <input type="time" name="pm_start" value="{{ old('pm_start', $rosterPmStart) }}" class="form-control form-control-sm w-auto" required>
                            <span class="text-muted small">to</span>
                            <input type="time" name="pm_end" value="{{ old('pm_end', $rosterPmEnd) }}" class="form-control form-control-sm w-auto" required>
                        </div>
                        @error('pm_start')<div class="text-danger small">{{ $message }}</div>@enderror
                        @error('pm_end')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="d-flex flex-wrap gap-2" data-chips="pm"></div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-btn="pm">+ Add staff</button>
                        </div>
                        <div class="d-none" aria-hidden="true">
                            @foreach($staff as $s)
                                <input class="roster-pm" type="checkbox" name="pm_staff[]" value="{{ $s->id }}" tabindex="-1"
                                    data-staff-id="{{ $s->id }}" data-staff-name="{{ $s->fullName() }}" data-staff-position="{{ $s->staffProfile->position ?? 'Staff' }}"
                                    {{ in_array($s->id, old('pm_staff', $rosterPmStaff)) ? 'checked' : '' }}>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="card p-3 mt-3">
                <div class="small text-muted mb-1">Not assigned — not bookable this week</div>
                <div id="rosterUnassigned" class="small"></div>
            </div>
            <button class="btn btn-spa mt-3">Save Roster</button>
        </form>
        {{-- Reusable "Add staff" floating box (modal) for the AM/PM cards --}}
        <div id="rosterModalOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1050; align-items:center; justify-content:center; padding:1rem;">
            <div class="card" role="dialog" aria-modal="true" aria-labelledby="rosterModalTitle" style="width:100%; max-width:430px; max-height:85vh; display:flex; flex-direction:column; border-radius:.75rem;">
                <div class="p-3 border-bottom">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <h6 id="rosterModalTitle" class="mb-0">Add staff</h6>
                            <div class="text-muted small">{{ $rosterWeekLabel }}</div>
                        </div>
                        <button type="button" class="btn-close" data-modal-close aria-label="Close"></button>
                    </div>
                    <input id="rosterModalSearch" type="search" class="form-control form-control-sm mt-2" placeholder="Search by name..." autocomplete="off">
                </div>
                <div id="rosterModalList" class="p-2" style="overflow-y:auto;"></div>
                <div class="p-3 border-top d-flex align-items-center justify-content-between gap-2">
                    <span id="rosterModalCount" class="small text-muted"></span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-modal-close>Cancel</button>
                        <button type="button" class="btn btn-sm btn-spa" data-modal-close>Done</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── TAB 3: SERVICE HOURS ─────────────────────────────────────── --}}
    <div class="tab-pane fade" id="tab-services">
        <div class="card p-3">
            <p class="text-muted small mb-3">Restrict specific services to certain hours. Leave blank to follow business hours.</p>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th>Service</th><th>Available From</th><th>Available Until</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($services as $service)
                            <tr>
                                <td>
                                    <strong>{{ $service->name }}</strong>
                                    <div class="small text-muted">{{ $service->category }} · {{ $service->duration_minutes }} min</div>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.availability.service-hours') }}" class="d-flex align-items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="service_id" value="{{ $service->id }}">
                                        <input type="time" name="available_from"
                                               value="{{ $service->available_from ? \Carbon\Carbon::parse($service->available_from)->format('H:i') : '' }}"
                                               class="form-control form-control-sm w-auto" placeholder="10:00">
                                </td>
                                <td>
                                        <input type="time" name="available_until"
                                               value="{{ $service->available_until ? \Carbon\Carbon::parse($service->available_until)->format('H:i') : '' }}"
                                               class="form-control form-control-sm w-auto" placeholder="21:00">
                                </td>
                                <td>
                                        <button class="btn btn-sm btn-spa">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No active services.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── TAB 4: BLOCKED SLOTS ────────────────────────────────────── --}}
    <div class="tab-pane fade" id="tab-blocked">
        <div class="card p-3 mb-4">
            <p class="text-muted small mb-3">Block specific dates or time slots from being bookable. Leave times blank to block the entire day.</p>
            <form method="POST" action="{{ route('admin.availability.blocked-slots.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label small mb-0">Date</label>
                    <input type="date" name="blocked_date" class="form-control form-control-sm" min="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">From</label>
                    <input type="time" name="start_time" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">To</label>
                    <input type="time" name="end_time" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">Staff (optional)</label>
                    <select name="personnel_id" class="form-select form-select-sm">
                        <option value="">All staff</option>
                        @foreach($staff as $s)
                            <option value="{{ $s->id }}">{{ $s->fullName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">Reason</label>
                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Holiday, maintenance...">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-spa">Block</button>
                </div>
            </form>
        </div>

        <div class="card p-3">
            <h6 class="mb-3">Upcoming Blocked Slots</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr><th>Date</th><th>Time</th><th>Staff</th><th>Reason</th><th>Blocked by</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($blockedSlots as $slot)
                            <tr>
                                <td>{{ $slot->blocked_date->format('M j, Y') }}</td>
                                <td>
                                    @if($slot->start_time && $slot->end_time)
                                        {{ \Carbon\Carbon::parse($slot->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($slot->end_time)->format('g:i A') }}
                                    @else
                                        <span class="text-muted">All day</span>
                                    @endif
                                </td>
                                <td>{{ $slot->personnel?->fullName() ?? 'All staff' }}</td>
                                <td>{{ $slot->reason ?? '—' }}</td>
                                <td class="small text-muted">{{ $slot->creator?->name ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.availability.blocked-slots.destroy', $slot->id) }}" class="d-inline"
                                          onsubmit="return confirm('Remove this blocked slot?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No blocked slots.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
    #rosterModalOverlay .roster-avatar {
        width: 32px; height: 32px; flex: 0 0 32px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 50%; background: #e9ecef; color: #495057;
        font-size: .75rem; font-weight: 600;
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const unassignedEl = document.getElementById('rosterUnassigned');
        if (!unassignedEl) return;

        // Single source of truth: the hidden am_staff[] / pm_staff[] checkboxes.
        // Chips and the modal render from them; the submitted form data is identical.
        const directory = {};
        const order = [];
        document.querySelectorAll('.roster-am').forEach((box) => {
            directory[box.dataset.staffId] = {
                name: box.dataset.staffName,
                position: box.dataset.staffPosition || 'Staff',
            };
            order.push(box.dataset.staffId);
        });

        const overlay = document.getElementById('rosterModalOverlay');
        const modalTitle = document.getElementById('rosterModalTitle');
        const modalSearch = document.getElementById('rosterModalSearch');
        const modalList = document.getElementById('rosterModalList');
        const modalCount = document.getElementById('rosterModalCount');
        let modalShift = 'am';

        const shiftLabel = (shift) => (shift === 'am' ? 'AM · Morning shift' : 'PM · Afternoon shift');
        const shiftShort = (shift) => (shift === 'am' ? 'AM' : 'PM');

        const box = (shift, id) => document.querySelector(
            (shift === 'am' ? '.roster-am' : '.roster-pm') + '[data-staff-id="' + id + '"]'
        );
        const isChecked = (shift, id) => {
            const b = box(shift, id);
            return !!(b && b.checked);
        };
        const unassignedIds = () => order.filter(
            (id) => !isChecked('am', id) && !isChecked('pm', id)
        );
        const shiftIds = (shift) => order.filter((id) => isChecked(shift, id));

        function initials(name) {
            const parts = (name || '').trim().split(/\s+/).filter(Boolean);
            return ((parts[0] ? parts[0][0] : '') + (parts[1] ? parts[1][0] : '')).toUpperCase() || '•';
        }

        function makeChip(shift, id) {
            const info = directory[id];
            const chip = document.createElement('span');
            chip.className = 'd-inline-flex align-items-center gap-1 border rounded px-2 py-1 small bg-light';

            const wrap = document.createElement('span');
            const name = document.createElement('span');
            name.className = 'fw-semibold';
            name.textContent = info.name;
            const pos = document.createElement('span');
            pos.className = 'd-block text-muted';
            pos.style.fontSize = '.75rem';
            pos.textContent = info.position;
            wrap.appendChild(name);
            wrap.appendChild(pos);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-close ms-1';
            remove.style.fontSize = '.6rem';
            remove.setAttribute('aria-label', 'Remove ' + info.name);
            remove.setAttribute('data-remove', shift);
            remove.dataset.staffId = id;

            chip.appendChild(wrap);
            chip.appendChild(remove);
            return chip;
        }

        function renderChips(shift) {
            const area = document.querySelector('[data-chips="' + shift + '"]');
            if (!area) return;
            area.innerHTML = '';
            document.querySelectorAll((shift === 'am' ? '.roster-am' : '.roster-pm')).forEach((b) => {
                if (b.checked) area.appendChild(makeChip(shift, b.dataset.staffId));
            });
            if (!area.children.length) {
                const empty = document.createElement('span');
                empty.className = 'text-muted small';
                empty.textContent = 'No staff assigned.';
                area.appendChild(empty);
            }
        }

        function renderUnassigned() {
            const names = unassignedIds().map((id) => directory[id].name);
            unassignedEl.textContent = names.length ? names.join(', ') : 'Everyone is assigned.';
        }

        function renderAddButtons() {
            const noneLeft = unassignedIds().length === 0;
            document.querySelectorAll('[data-add-btn]').forEach((btn) => {
                btn.disabled = noneLeft;
                btn.classList.toggle('d-none', noneLeft);
            });
        }

        function syncAll() {
            renderChips('am');
            renderChips('pm');
            renderUnassigned();
            renderAddButtons();
        }

        // ── Modal ────────────────────────────────────────────────
        function renderModalCount() {
            const n = shiftIds(modalShift).length;
            modalCount.textContent = n + ' staff in ' + shiftShort(modalShift) + ' shift';
        }

        function makeRow(id) {
            const info = directory[id];
            const other = modalShift === 'am' ? 'pm' : 'am';

            const row = document.createElement('div');
            row.className = 'd-flex align-items-center gap-2 px-2 py-2 border-bottom';

            const avatar = document.createElement('span');
            avatar.className = 'roster-avatar';
            avatar.textContent = initials(info.name);

            const text = document.createElement('span');
            text.className = 'flex-grow-1';
            const name = document.createElement('span');
            name.className = 'fw-semibold d-block';
            name.textContent = info.name;
            const pos = document.createElement('span');
            pos.className = 'text-muted small d-block';
            pos.textContent = info.position;
            text.appendChild(name);
            text.appendChild(pos);

            const action = document.createElement('span');
            if (isChecked(modalShift, id)) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-success';
                btn.setAttribute('data-mremove', modalShift);
                btn.dataset.staffId = id;
                btn.textContent = 'Added ✓';
                action.appendChild(btn);
            } else if (isChecked(other, id)) {
                const label = document.createElement('button');
                label.type = 'button';
                label.className = 'btn btn-sm btn-outline-secondary';
                label.disabled = true;
                label.textContent = 'In ' + shiftShort(other) + ' shift';
                action.appendChild(label);
            } else {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-spa';
                btn.setAttribute('data-madd', modalShift);
                btn.dataset.staffId = id;
                btn.textContent = '+ Add';
                action.appendChild(btn);
            }

            row.appendChild(avatar);
            row.appendChild(text);
            row.appendChild(action);
            return row;
        }

        function renderModalList() {
            const q = (modalSearch.value || '').trim().toLowerCase();
            modalList.innerHTML = '';
            const ids = order.filter((id) => directory[id].name.toLowerCase().includes(q));
            if (!ids.length) {
                const empty = document.createElement('div');
                empty.className = 'text-muted small px-2 py-3 text-center';
                empty.textContent = 'No staff match your search.';
                modalList.appendChild(empty);
            } else {
                ids.forEach((id) => modalList.appendChild(makeRow(id)));
            }
            renderModalCount();
        }

        function openModal(shift) {
            modalShift = shift;
            modalTitle.textContent = 'Add staff — ' + shiftLabel(shift);
            modalSearch.value = '';
            renderModalList();
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            modalSearch.focus();
        }

        function closeModal() {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
            syncAll();
        }

        function isOpen() {
            return overlay.style.display === 'flex';
        }

        modalSearch.addEventListener('input', renderModalList);

        modalList.addEventListener('click', (e) => {
            const add = e.target.closest('[data-madd]');
            if (add) {
                const shift = add.getAttribute('data-madd');
                const id = add.dataset.staffId;
                const target = box(shift, id);
                const other = box(shift === 'am' ? 'pm' : 'am', id);
                if (target) target.checked = true;
                if (other) other.checked = false;
                renderModalList();
                return;
            }
            const remove = e.target.closest('[data-mremove]');
            if (remove) {
                const b = box(remove.getAttribute('data-mremove'), remove.dataset.staffId);
                if (b) b.checked = false;
                renderModalList();
            }
        });

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.closest('[data-modal-close]')) closeModal();
        });

        document.addEventListener('click', (e) => {
            const remove = e.target.closest('[data-remove]');
            if (remove) {
                const b = box(remove.getAttribute('data-remove'), remove.dataset.staffId);
                if (b) b.checked = false;
                syncAll();
                return;
            }

            const add = e.target.closest('[data-add-btn]');
            if (add) {
                if (add.disabled) return;
                openModal(add.getAttribute('data-add-btn'));
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isOpen()) closeModal();
        });

        syncAll();

        // Keep the roster tab active when arriving with ?week / ?copy_from.
        const params = new URLSearchParams(window.location.search);
        if (params.has('week') || params.has('copy_from')) {
            const trigger = document.querySelector('[data-bs-target="#tab-staff"]');
            if (trigger && window.bootstrap && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(trigger).show();
            }
        }
    })();
</script>
@endpush
