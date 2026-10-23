{{-- Carries the Branch/Home Service Booking wizard's selections forward
     through the GET step chain as hidden inputs. Purely a UX convenience —
     nothing here is trusted; every step re-validates, and store()/homeStore()
     re-validate again from scratch via StoreWebBranchBookingRequest /
     StoreWebHomeBookingRequest.
     Expects (any may be null/absent): $service, $variant (Branch package
     tier id), $date, $time, $personnel, $notes, $guests (Branch
     down-payment bookings without variants — the Details step's guest
     count), $address (Home Service only — the array BookingController's
     addressFieldsFromRequest() shape returns: source_client_address_id,
     street_address, barangay, city_municipality, province, postal_code) --}}
@if(!empty($service))<input type="hidden" name="service" value="{{ $service }}">@endif
@if(!empty($variant))<input type="hidden" name="variant" value="{{ $variant }}">@endif
@if(!empty($date))<input type="hidden" name="date" value="{{ $date }}">@endif
@if(!empty($time))<input type="hidden" name="time" value="{{ $time }}">@endif
@if(!empty($personnel))<input type="hidden" name="personnel" value="{{ $personnel }}">@endif
@if(!empty($staff) && is_array($staff))
    @foreach($staff as $positionId => $value)
        <input type="hidden" name="staff[{{ $positionId }}]" value="{{ $value }}">
    @endforeach
@endif
@if(!empty($notes))<input type="hidden" name="notes" value="{{ $notes }}">@endif
@if(!empty($guests))<input type="hidden" name="guests" value="{{ $guests }}">@endif
@if(!empty($extras) && is_array($extras))
    @foreach($extras as $index => $extra)
        <input type="hidden" name="extras[{{ $index }}][service_id]" value="{{ $extra['service']->id }}">
        @if(!empty($extra['variant_label']))
            <input type="hidden" name="extras[{{ $index }}][variant_label]" value="{{ $extra['variant_label'] }}">
        @endif
        @if(!empty($extra['personnel_id']))
            <input type="hidden" name="extras[{{ $index }}][personnel_id]" value="{{ $extra['personnel_id'] }}">
        @endif
        @if(!empty($extra['start_time']))
            <input type="hidden" name="extras[{{ $index }}][start_time]" value="{{ $extra['start_time'] }}">
        @endif
    @endforeach
@endif
@if(!empty($address))
    @foreach(['source_client_address_id', 'street_address', 'barangay', 'city_municipality', 'province', 'postal_code'] as $field)
        @if(!empty($address[$field]))<input type="hidden" name="{{ $field }}" value="{{ $address[$field] }}">@endif
    @endforeach
@endif
