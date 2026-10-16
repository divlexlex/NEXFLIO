{{--
    Shared inventory category dropdown (Inventory page). Categories come
    from the inventory_categories table (id order keeps the classics
    first), so newly added ones appear with no code changes.

    Usage:
        @include('partials.inventory-category-select', ['current' => $category, 'categories' => $categories])

    Params:
        current     currently selected category name (null = none/all)
        categories  list of category names (defaults to the classic four
                    when the caller predates the table)
        showAll     add an "All categories" option (default false)
        label       optional small caption shown before the select
        id          optional id for the <select>
        form        when true, changing submits the enclosing GET form
                    (filter bar) instead of navigating directly
--}}
@php
    $categories = $categories ?? ['Facial', 'Massage', 'Nails', 'Aesthetic'];
    $showAll = $showAll ?? false;
    $current = $current ?? null;
    $hasSelection = $current !== null && in_array($current, $categories, true);
    $inForm = $form ?? false;
@endphp
<div class="spa-category-select d-inline-flex align-items-center">
    @isset($label)
        <span class="spa-category-select__label me-2">{{ $label }}</span>
    @endisset
    <i class="bi bi-folder2 me-2 text-gold"></i>
    <select class="spa-category-select__control" name="category"
            @isset($id) id="{{ $id }}" @endisset
            data-base="{{ url()->current() }}"
            aria-label="Inventory category"
            @if(! $inForm)
                onchange="const v = this.value; window.location.href = v ? this.dataset.base + '?category=' + v : this.dataset.base;"
            @endif
            >
        @if($showAll)
            <option value="" {{ $current === null || ! $hasSelection ? 'selected' : '' }}>All categories</option>
        @endif
        @foreach($categories as $name)
            <option value="{{ $name }}" {{ $current === $name ? 'selected' : '' }}>{{ $name }}</option>
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
