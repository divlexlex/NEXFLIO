@extends('layouts.admin')

@section('title', 'Promotions')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Promotions</h1>
        <p class="text-muted small mb-0">Manage and create special offers, discounts, and campaigns.</p>
    </div>
    <a href="{{ route('admin.promos.create') }}" class="btn btn-spa">
        <i class="bi bi-plus-lg me-1"></i>Add Promotion
    </a>
</div>

@php($tabs = ['all' => 'All', 'active' => 'Active', 'scheduled' => 'Scheduled', 'expired' => 'Expired', 'inactive' => 'Inactive'])

<ul class="nav nav-pills mb-3 gap-2" role="tablist">
    @foreach($tabs as $key => $label)
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#tab-{{ $key }}" type="button" role="tab">
                {{ $label }}
                @php($count = $key === 'all' ? $promos->count() : $promosByStatus->get($key, collect())->count())
                <span class="badge text-bg-secondary ms-1">{{ $count }}</span>
            </button>
        </li>
    @endforeach
</ul>

<div class="tab-content">
    @foreach($tabs as $key => $label)
        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab-{{ $key }}" role="tabpanel">
            @php($rows = $key === 'all' ? $promos : $promosByStatus->get($key, collect()))
            <div class="card p-3">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr><th style="width:60px;"></th><th>Title</th><th>Type</th><th>Discount</th><th>Period</th><th>Status</th><th class="text-end"></th></tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $promo)
                                <tr>
                                    <td>@include('admin.partials.thumb', ['url' => $promo->image_url])</td>
                                    <td>
                                        <strong>{{ $promo->title }}</strong>
                                        @if($promo->services->isNotEmpty())
                                            <div class="small text-muted">{{ $promo->services->pluck('name')->implode(', ') }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ match($promo->discount_type) {
                                            'percentage' => 'text-bg-info',
                                            'fixed_amount' => 'text-bg-primary',
                                            default => 'text-bg-secondary',
                                        } }}">{{ \Illuminate\Support\Str::headline($promo->discount_type) }}</span>
                                    </td>
                                    <td>
                                        @if($promo->discount_type === 'bundle')
                                            ₱{{ number_format($promo->price, 2) }}
                                        @else
                                            {{ $promo->discountLabel() }}
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if($promo->starts_at || $promo->ends_at)
                                            {{ $promo->starts_at?->format('M j, Y') ?? 'Any time' }} &ndash; {{ $promo->ends_at?->format('M j, Y') ?? 'No end date' }}
                                        @else
                                            <span class="text-muted">Always on</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ match($promo->displayStatus()) {
                                            'active' => 'text-bg-success',
                                            'scheduled' => 'text-bg-warning',
                                            'expired' => 'text-bg-secondary',
                                            default => 'text-bg-light',
                                        } }}">{{ ucfirst($promo->displayStatus()) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.promos.edit', $promo->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No promotions {{ $key === 'all' ? 'yet' : 'in this status' }}.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
