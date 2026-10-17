@extends('layouts.admin')

@section('title', 'Availability')

@section('content')
<h1 class="h3 mb-4">Availability Management</h1>

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
            <p class="text-muted small mb-3">Set the store's opening and closing times for each day. These apply to all booking slots. A closing time earlier than opening means the store closes after midnight.</p>
            <div class="d-flex justify-content-end gap-2 mb-3">
                @if($businessHoursEditable)
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.availability') }}#tab-hours">Cancel</a>
                    <button type="button" class="btn btn-sm btn-secondary" disabled><i class="bi bi-pencil me-1"></i>Edit</button>
                @else
                    <a class="btn btn-sm btn-spa" href="{{ route('admin.availability', ['edit' => 1]) }}#tab-hours"><i class="bi bi-pencil me-1"></i>Edit</a>
                @endif
            </div>
            @if($businessHoursEditable)
            <form method="POST" action="{{ route('admin.availability.business-hours') }}">
                @csrf
            @endif
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr><th>Day</th><th>Open</th><th>Close</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @php($days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'])
                            @foreach($days as $i => $day)
                                @php($hours = $businessHours->firstWhere('day_of_week', $i))
                                @php($defaults = \App\Models\BusinessHours::defaultsForDay($i))
                                @php($openValue = $hours?->open_at ? \Carbon\Carbon::parse($hours->open_at)->format('H:i') : substr($defaults['open_at'], 0, 5))
                                @php($closeValue = $hours?->close_at ? \Carbon\Carbon::parse($hours->close_at)->format('H:i') : substr($defaults['close_at'], 0, 5))
                                <tr>
                                    <td class="fw-semibold">{{ $day }}</td>
                                    @if($businessHoursEditable)
                                        <td>
                                            <input type="time" name="hours[{{ $i }}][open_at]"
                                                   value="{{ $openValue }}"
                                                   class="form-control form-control-sm w-auto"
                                                   {{ !$hours?->is_open ? 'disabled' : '' }}>
                                        </td>
                                        <td>
                                            <input type="time" name="hours[{{ $i }}][close_at]"
                                                   value="{{ $closeValue }}"
                                                   class="form-control form-control-sm w-auto"
                                                   {{ !$hours?->is_open ? 'disabled' : '' }}>
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="hours[{{ $i }}][is_open]" value="0">
                                                <input class="form-check-input" type="checkbox"
                                                       name="hours[{{ $i }}][is_open]" value="1"
                                                       {{ ($hours?->is_open ?? true) ? 'checked' : '' }}
                                                       onchange="this.closest('tr').querySelectorAll('input[type=time]').forEach(el => el.disabled = !this.checked)">
                                                <label class="form-check-label small">{{ ($hours?->is_open ?? true) ? 'Open' : 'Closed' }}</label>
                                            </div>
                                        </td>
                                    @else
                                        <td>{{ $hours?->is_open ? \Carbon\Carbon::parse($hours->open_at)->format('g:i A') : '—' }}</td>
                                        <td>{{ $hours?->is_open ? \Carbon\Carbon::parse($hours->close_at)->format('g:i A') : '—' }}</td>
                                        <td>
                                            <span class="badge {{ $hours?->is_open ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $hours?->is_open ? 'Open' : 'Closed' }}</span>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($businessHoursEditable)
                <button class="btn btn-spa mt-2">Save Business Hours</button>
            </form>
            @endif
        </div>
    </div>

    {{-- ── TAB 2: WEEKLY ROSTER ───────────────────────────────────── --}}
    <div class="tab-pane fade" id="tab-staff">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.availability', ['week' => $rosterPrevWeek]) }}#tab-staff">‹ Prev</a>
            <strong>{{ $rosterWeekLabel }}</strong>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.availability', ['week' => $rosterNextWeek]) }}#tab-staff">Next ›</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.availability') }}#tab-staff">This week</a>
            <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
                @if($rosterEditable)
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.availability', ['week' => $rosterWeekStart, 'copy_from' => $rosterPrevWeek]) }}#tab-staff">Copy previous week</a>
                    @if($rosterHasAssignments)
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.availability', ['week' => $rosterWeekStart]) }}#tab-staff">Cancel</a>
                    @endif
                    <button type="button" class="btn btn-sm btn-secondary" disabled><i class="bi bi-pencil me-1"></i>Edit</button>
                @else
                    <a class="btn btn-sm btn-spa" href="{{ route('admin.availability', ['week' => $rosterWeekStart, 'edit' => 1]) }}#tab-staff"><i class="bi bi-pencil me-1"></i>Edit</a>
                @endif
            </div>
        </div>
        @if($rosterEditable)
        <form method="POST" action="{{ route('admin.availability.weekly-roster') }}">
            @csrf
            <input type="hidden" name="week_start" value="{{ $rosterWeekStart }}">
            @if($errors->has('assignments') || $errors->has('week_start'))
                <div class="alert alert-danger py-2 small">
                    {{ $errors->first('assignments') }} {{ $errors->first('week_start') }}
                </div>
            @endif
        @endif
            <div class="card p-3 mb-3">
                <h6 class="mb-3">{{ $rosterWeekLabel }}</h6>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Staff</th><th>Category</th><th>Start of shift</th><th>End of shift</th>
                                @if($rosterEditable)<th></th>@endif
                            </tr>
                        </thead>
                        <tbody id="rosterBody">
                            @foreach($rosterAssignments as $row)
                                <tr>
                                    <td>
                                        <span class="fw-semibold">{{ $row['name'] }}</span>
                                        @if($rosterEditable)
                                            <input type="hidden" name="assignments[{{ $loop->index }}][user_id]" value="{{ $row['user_id'] }}">
                                        @endif
                                    </td>
                                    <td><span class="badge text-bg-light border">{{ $row['position'] }}</span></td>
                                    <td>
                                        @if($rosterEditable)
                                            <input type="time" name="assignments[{{ $loop->index }}][start_time]" value="{{ $row['start'] }}" required class="form-control form-control-sm w-auto">
                                        @else
                                            <span class="roster-shift-time">{{ \Carbon\Carbon::parse($row['start'])->format('g:i A') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($rosterEditable)
                                            <input type="time" name="assignments[{{ $loop->index }}][end_time]" value="{{ $row['end'] }}" required class="form-control form-control-sm w-auto">
                                        @else
                                            <span class="roster-shift-time">{{ \Carbon\Carbon::parse($row['end'])->format('g:i A') }}</span>
                                        @endif
                                    </td>
                                    @if($rosterEditable)
                                        <td>
                                            <button type="button" class="btn-close" style="font-size:.7rem" aria-label="Remove {{ $row['name'] }}" data-remove="{{ $row['user_id'] }}"></button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p id="rosterEmpty" class="text-muted small mb-0 mt-2" @if(count($rosterAssignments) > 0) hidden @endif>No staff assigned yet — everyone falls back to the standard schedule this week.</p>
                @if($rosterEditable)
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="rosterAddBtn">+ Add staff</button>
                    </div>
                @endif
            </div>
            <div class="card p-3 mt-3">
                <div class="small text-muted mb-1">Not assigned — not bookable this week</div>
                <div id="rosterUnassigned" class="small"></div>
            </div>
            @if($rosterEditable)
                <button class="btn btn-spa mt-3">Save Roster</button>
        </form>
        @endif
        @if($rosterEditable)
        {{-- Reusable "Add staff" floating box (modal) --}}
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
                    <div class="d-flex flex-wrap gap-1 mt-2" id="rosterCategoryPills">
                        <button type="button" class="btn btn-sm btn-outline-secondary active" data-cat="All">All</button>
                        @foreach($rosterPositions as $rosterPosition)
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-cat="{{ $rosterPosition }}">{{ $rosterPosition }}</button>
                        @endforeach
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
        @endif
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

        // Locked (read-only) weeks render times as plain text; edit mode
        // renders time inputs and the modal-driven picker.
        const EDITABLE = @json($rosterEditable);

        // Single source of truth: the rows array below. The table (with its
        // assignments[i][..] inputs) renders from it; the modal and the
        // Not-assigned strip derive from the same state.
        const directory = {};
        const order = [];
        @json($rosterStaff).forEach((s) => {
            const id = String(s.id);
            directory[id] = { name: s.name, position: s.position || 'Staff' };
            order.push(id);
        });

        let rows = @json($rosterAssignments).map((r) => ({
            id: String(r.user_id),
            name: r.name,
            position: r.position || 'Staff',
            start: r.start,
            end: r.end,
        }));

        // Position pills group in table order, so a newly added position
        // appears without code changes (see $rosterPositions above).
        const CATEGORIES = @json($rosterPositions);

        const overlay = document.getElementById('rosterModalOverlay');
        const modalSearch = document.getElementById('rosterModalSearch');
        const modalList = document.getElementById('rosterModalList');
        const modalCount = document.getElementById('rosterModalCount');
        const catPills = document.getElementById('rosterCategoryPills');
        const addBtn = document.getElementById('rosterAddBtn');
        const tbody = document.getElementById('rosterBody');
        const emptyEl = document.getElementById('rosterEmpty');

        let pending = new Set();
        let modalCat = 'All';

        const rosteredIds = () => new Set(rows.map((r) => r.id));
        const unassignedIds = () => order.filter((id) => !rosteredIds().has(id));

        function initials(name) {
            const parts = (name || '').trim().split(/\s+/).filter(Boolean);
            return ((parts[0] ? parts[0][0] : '') + (parts[1] ? parts[1][0] : '')).toUpperCase() || '•';
        }

        function makeTimeInput(name, value) {
            const input = document.createElement('input');
            input.type = 'time';
            input.name = name;
            input.value = value;
            input.required = true;
            input.className = 'form-control form-control-sm w-auto';
            return input;
        }

        // "HH:MM" -> 12-hour display text, e.g. "14:00" -> "2:00 PM".
        function fmt12(hhmm) {
            const m = /^(\d{1,2}):(\d{2})/.exec(hhmm || '');
            if (!m) return hhmm || '';
            let h = parseInt(m[1], 10);
            const suffix = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            if (h === 0) h = 12;
            return h + ':' + m[2] + ' ' + suffix;
        }

        function renderRows() {
            tbody.innerHTML = '';
            rows.forEach((row, i) => {
                const tr = document.createElement('tr');

                const nameTd = document.createElement('td');
                const name = document.createElement('span');
                name.className = 'fw-semibold';
                name.textContent = row.name;
                nameTd.appendChild(name);
                if (EDITABLE) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'assignments[' + i + '][user_id]';
                    hidden.value = row.id;
                    nameTd.appendChild(hidden);
                }

                const catTd = document.createElement('td');
                const pill = document.createElement('span');
                pill.className = 'badge text-bg-light border';
                pill.textContent = row.position;
                catTd.appendChild(pill);

                const startTd = document.createElement('td');
                const endTd = document.createElement('td');
                if (EDITABLE) {
                    startTd.appendChild(makeTimeInput('assignments[' + i + '][start_time]', row.start));
                    endTd.appendChild(makeTimeInput('assignments[' + i + '][end_time]', row.end));
                } else {
                    const startSpan = document.createElement('span');
                    startSpan.className = 'roster-shift-time';
                    startSpan.textContent = fmt12(row.start);
                    startTd.appendChild(startSpan);
                    const endSpan = document.createElement('span');
                    endSpan.className = 'roster-shift-time';
                    endSpan.textContent = fmt12(row.end);
                    endTd.appendChild(endSpan);
                }

                tr.appendChild(nameTd);
                tr.appendChild(catTd);
                tr.appendChild(startTd);
                tr.appendChild(endTd);

                if (EDITABLE) {
                    const rmTd = document.createElement('td');
                    const rm = document.createElement('button');
                    rm.type = 'button';
                    rm.className = 'btn-close';
                    rm.style.fontSize = '.7rem';
                    rm.setAttribute('aria-label', 'Remove ' + row.name);
                    rm.setAttribute('data-remove', row.id);
                    rmTd.appendChild(rm);
                    tr.appendChild(rmTd);
                }

                tbody.appendChild(tr);
            });
            emptyEl.hidden = rows.length > 0;
        }

        function renderUnassigned() {
            const inRoster = rosteredIds();
            const groups = {};
            order.forEach((id) => {
                if (inRoster.has(id)) return;
                const pos = directory[id].position;
                (groups[pos] = groups[pos] || []).push(id);
            });
            unassignedEl.innerHTML = '';
            const cats = [
                ...CATEGORIES.filter((c) => groups[c]),
                ...Object.keys(groups).filter((c) => !CATEGORIES.includes(c)).sort(),
            ];
            if (!cats.length) {
                unassignedEl.textContent = 'Everyone is assigned.';
                return;
            }
            cats.forEach((cat) => {
                const div = document.createElement('div');
                div.className = 'mb-1';
                const badge = document.createElement('span');
                badge.className = 'badge text-bg-light border me-1';
                badge.textContent = cat;
                div.appendChild(badge);
                div.appendChild(document.createTextNode(
                    groups[cat].map((id) => directory[id].name).join(', ')
                ));
                unassignedEl.appendChild(div);
            });
        }

        function renderAddButton() {
            if (!addBtn) return;
            const noneLeft = unassignedIds().length === 0;
            addBtn.disabled = noneLeft;
            addBtn.classList.toggle('d-none', noneLeft);
        }

        function syncAll() {
            renderRows();
            renderUnassigned();
            renderAddButton();
        }

        // ── Modal (edit mode only) ───────────────────────────────
        if (overlay) {
        function renderModalCount() {
            modalCount.textContent = pending.size + ' staff in roster';
        }

        function syncCatPills() {
            catPills.querySelectorAll('[data-cat]').forEach((b) => {
                b.classList.toggle('active', b.getAttribute('data-cat') === modalCat);
            });
        }

        function makeRow(id) {
            const info = directory[id];

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
            if (pending.has(id)) {
                const done = document.createElement('button');
                done.type = 'button';
                done.className = 'btn btn-sm btn-outline-success';
                done.disabled = true;
                done.textContent = 'Added ✓';
                action.appendChild(done);
            } else {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-spa';
                btn.setAttribute('data-madd', id);
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
            const ids = order.filter((id) => {
                const info = directory[id];
                const catOk = modalCat === 'All' || info.position === modalCat;
                return catOk && info.name.toLowerCase().includes(q);
            });
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

        function openModal() {
            pending = new Set(rows.map((r) => r.id));
            modalCat = 'All';
            modalSearch.value = '';
            syncCatPills();
            renderModalList();
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            modalSearch.focus();
        }

        function applyPending() {
            const next = [];
            order.forEach((id) => {
                if (!pending.has(id)) return;
                const existing = rows.find((r) => r.id === id);
                next.push(existing || {
                    id: id,
                    name: directory[id].name,
                    position: directory[id].position,
                    start: '10:00',
                    end: '21:00',
                });
            });
            rows = next;
        }

        function closeModal() {
            applyPending();
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
            if (!add) return;
            pending.add(add.getAttribute('data-madd'));
            renderModalList();
        });

        catPills.addEventListener('click', (e) => {
            const pill = e.target.closest('[data-cat]');
            if (!pill) return;
            modalCat = pill.getAttribute('data-cat');
            syncCatPills();
            renderModalList();
        });

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.closest('[data-modal-close]')) closeModal();
        });

        tbody.addEventListener('click', (e) => {
            const remove = e.target.closest('[data-remove]');
            if (!remove) return;
            rows = rows.filter((r) => r.id !== remove.getAttribute('data-remove'));
            syncAll();
        });

        addBtn.addEventListener('click', () => {
            if (addBtn.disabled) return;
            openModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isOpen()) closeModal();
        });
        }

        syncAll();

        // Keep the roster tab active when arriving with ?week / ?copy_from / ?edit.
        const params = new URLSearchParams(window.location.search);
        if (params.has('week') || params.has('copy_from') || params.has('edit')) {
            const trigger = document.querySelector('[data-bs-target="#tab-staff"]');
            if (trigger && window.bootstrap && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(trigger).show();
            }
        }
    })();
</script>
@endpush
