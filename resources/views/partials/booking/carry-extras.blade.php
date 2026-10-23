{{-- Carries the Branch wizard's chosen EXTRA services through the GET step
     chain (Problem #4). Server-trusted rows only — the controller re-resolves
     every price and re-validates each row in store(). --}}
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
