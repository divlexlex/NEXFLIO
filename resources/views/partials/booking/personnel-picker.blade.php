{{-- Spa Personnel picker for the schedule step.
     Regular services: flat radio list (+ "No preference").
     Packages with required positions: one radio group per role.
     Expects: $service, $isMultiStaff, $eligiblePersonnel, $eligibleByPosition,
              $personnelParam, $staffSelection, $scheduleRoute, plus hidden
              carry fields already present on the wrapping form. --}}
<label class="form-label nx-eyebrow">Preferred Spa Personnel</label>

@if($isMultiStaff)
    @if($service->positions->isEmpty())
        <div class="alert alert-warning mb-0">This package has no employee categories configured. Ask the spa to set them on the service.</div>
    @else
        <p class="small nx-text-secondary mb-3">This package needs one staff member per role. Pick each, or leave as no preference for the manager to assign.</p>
        @foreach($service->positions as $position)
            @php
                $pool = $eligibleByPosition->get($position->id, collect());
                $selected = $staffSelection[$position->id] ?? null;
            @endphp
            <div class="mb-3">
                <div class="small fw-semibold mb-2">{{ $position->name }}</div>
                @if($pool->isEmpty())
                    <div class="alert alert-warning py-2 mb-0 small">No {{ $position->name }} available on this date.</div>
                @else
                    <div class="row g-2">
                        <div class="col-sm-6 col-lg-4">
                            <label class="nx-select-card d-flex align-items-center gap-2 {{ $selected === 'any' ? 'is-selected' : '' }}">
                                <input type="radio" name="staff[{{ $position->id }}]" value="any"
                                       {{ $selected === 'any' ? 'checked' : '' }}
                                       onchange="this.form.submit()">
                                <span>No preference<br><small class="nx-text-secondary">Manager assigns</small></span>
                            </label>
                        </div>
                        @foreach($pool as $person)
                            <div class="col-sm-6 col-lg-4">
                                <label class="nx-select-card d-flex align-items-center gap-2 {{ (string) $selected === (string) $person->id ? 'is-selected' : '' }}">
                                    <input type="radio" name="staff[{{ $position->id }}]" value="{{ $person->id }}"
                                           {{ (string) $selected === (string) $person->id ? 'checked' : '' }}
                                           onchange="this.form.submit()">
                                    <span>{{ $person->name }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    @endif
@else
    @if($eligiblePersonnel->isEmpty())
        <div class="alert alert-warning mb-0">No Spa Personnel are available on this date. Please choose a different date.</div>
    @else
        <div class="row g-2">
            <div class="col-sm-6 col-lg-4">
                <label class="nx-select-card d-flex align-items-center gap-2 {{ $personnelParam === 'any' ? 'is-selected' : '' }}">
                    <input type="radio" name="personnel" value="any" {{ $personnelParam === 'any' ? 'checked' : '' }} onchange="this.form.submit()">
                    <span>No preferred personnel<br><small class="nx-text-secondary">Manager will assign staff</small></span>
                </label>
            </div>
            @foreach($eligiblePersonnel as $person)
                <div class="col-sm-6 col-lg-4">
                    <label class="nx-select-card d-flex align-items-center gap-2 {{ (string) $personnelParam === (string) $person->id ? 'is-selected' : '' }}">
                        <input type="radio" name="personnel" value="{{ $person->id }}" {{ (string) $personnelParam === (string) $person->id ? 'checked' : '' }} onchange="this.form.submit()">
                        <span>{{ $person->name }}</span>
                    </label>
                </div>
            @endforeach
        </div>
    @endif
@endif
