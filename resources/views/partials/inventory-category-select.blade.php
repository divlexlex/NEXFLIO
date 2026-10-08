{{--
    Shared inventory category dropdown (Inventory page).

    Usage:
        @include('partials.inventory-category-select', ['current' => $category])

    Params:
        current     currently selected category key (null = none/all)
        showAll     add an "All categories" option (default false)
        label       optional small caption shown before the select
        id          optional id for the <select>
--}}
@php
    $categories = ['facial' => 'Facial', 'massage' => 'Massage', 'nails' => 'Nails', 'aesthetic' => 'Aesthetic'];
    $showAll = $showAll ?? false;
    $current = $current ?? null;
    $hasSelection = $current !== null && array_key_exists($current, $categories);
@endphp
<div class="spa-category-select d-inline-flex align-items-center">
    @isset($label)
        <span class="spa-category-select__label me-2">{{ $label }}</span>
    @endisset
    <i class="bi bi-folder2 me-2 text-gold"></i>
    <select class="spa-category-select__control"
            @isset($id) id="{{ $id }}" @endisset
            data-base="{{ url()->current() }}"
            aria-label="Inventory category"
            onchange="const v = this.value; window.location.href = v ? this.dataset.base + '?category=' + v : this.dataset.base;">
        @if($showAll)
            <option value="" {{ $current === null || ! $hasSelection ? 'selected' : '' }}>All categories</option>
        @endif
        @foreach($categories as $key => $label)
            <option value="{{ $key }}" {{ $current === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <i class="bi bi-chevron-down spa-category-select__caret"></i>
</div>

@once
@push('head')
<style>
    .spa-category-select {
        position: relative;
        background: var(--nex-surface);
        border: 1px solid var(--nex-border);
        border-radius: 999px;
        padding: .35rem .85rem;
        transition: border-color .2s, box-shadow .2s;
    }
    .spa-category-select:focus-within {
        border-color: var(--nex-accent);
        box-shadow: 0 0 0 3px rgba(181, 137, 90, .28);
    }
    .spa-category-select__label {
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--nex-muted);
        white-space: nowrap;
    }
    .spa-category-select__control {
        appearance: none;
        -webkit-appearance: none;
        background: transparent;
        border: 0;
        outline: 0;
        color: var(--nex-text);
        font-weight: 600;
        padding: .1rem 1.25rem .1rem 0;
        cursor: pointer;
    }
    .spa-category-select__control option {
        color: var(--nex-text);
        background: var(--nex-surface);
        font-weight: 500;
    }
    .spa-category-select__caret {
        position: absolute;
        right: .8rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: .7rem;
        color: var(--nex-muted);
        pointer-events: none;
    }
</style>
@endpush
@endonce
