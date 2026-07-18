{{-- Small image thumbnail with placeholder fallback. Expects: $url (nullable) --}}
@if(!empty($url))
    <img src="{{ $url }}" alt="" class="rounded" style="width:44px;height:44px;object-fit:cover;">
@else
    <span class="d-inline-flex align-items-center justify-content-center rounded text-muted"
          style="width:44px;height:44px;background:var(--spa-tan);">
        <i class="bi bi-image"></i>
    </span>
@endif
