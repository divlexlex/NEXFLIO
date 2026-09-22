{{-- Carries the Branch/Home Service Booking wizard's selections forward
     through the GET step chain as hidden inputs. Purely a UX convenience —
     nothing here is trusted; every step re-validates, and store()/homeStore()
     re-validate again from scratch via StoreWebBranchBookingRequest /
     StoreWebHomeBookingRequest.
     Expects (any may be null/absent): $service, $date, $time, $personnel,
     $notes, $address (Home Service only — the array BookingController's
     addressFieldsFromRequest() shape returns: source_client_address_id,
     street_address, barangay, city_municipality, province, postal_code) --}}
@if(!empty($service))<input type="hidden" name="service" value="{{ $service }}">@endif
@if(!empty($date))<input type="hidden" name="date" value="{{ $date }}">@endif
@if(!empty($time))<input type="hidden" name="time" value="{{ $time }}">@endif
@if(!empty($personnel))<input type="hidden" name="personnel" value="{{ $personnel }}">@endif
@if(!empty($notes))<input type="hidden" name="notes" value="{{ $notes }}">@endif
@if(!empty($address))
    @foreach(['source_client_address_id', 'street_address', 'barangay', 'city_municipality', 'province', 'postal_code'] as $field)
        @if(!empty($address[$field]))<input type="hidden" name="{{ $field }}" value="{{ $address[$field] }}">@endif
    @endforeach
@endif
