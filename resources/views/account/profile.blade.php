{{-- Client Profile — Account + Personal + Address (Client Profile + Address
     schema). Behind ['auth', 'role:4'] — see routes/web.php. One combined
     form posts to ClientAccountController@updateProfile, which updates
     users (email/password/derived name), client_profiles, and the default
     client_addresses row in a single transaction — see that method for the
     authoritative validation. $profile/$defaultAddress are both nullable:
     an existing Client who registered before this schema existed simply
     has no row yet, which is a normal state, not an error — this page
     shows a "Complete your profile" prompt instead of crashing, and the
     form works either way (filling in Personal/Address for the first time,
     or editing what's already there). Role is never rendered as an
     editable field anywhere on this page.

     Simplified layout (spa-app-inspired): a left sidebar (avatar, completion
     meter, vertical icon tabs) and ONE big card on the right whose content
     switches per tab — Bootstrap's native pill/tab-pane JS (already used by
     account/bookings.blade.php), no extra JS needed. All three sections
     stay in the DOM as tab-panes (just hidden, not removed), so the single
     "Save Changes" button at the bottom still submits every field
     regardless of which tab is showing. --}}
@extends('layouts.public')

@section('title', 'My Profile')

@section('content')
@php
    // Which tab opens active — defaults to Account, but jumps to whichever
    // section actually has a validation error after a failed Save, so an
    // error on a hidden pane (Personal/Address) is never silently invisible
    // behind the wrong tab. Pure server-side, no JS required.
    $activeProfileTab = 'profile-account';
    if ($errors->hasAny(['first_name', 'middle_name', 'last_name', 'gender', 'birthdate', 'mobile_number'])) {
        $activeProfileTab = 'profile-personal';
    } elseif ($errors->hasAny(['street_address', 'barangay', 'city_municipality', 'province', 'postal_code'])) {
        $activeProfileTab = 'profile-address';
    }
@endphp
<section class="nx-section" style="background: var(--nx-bg-page); padding-top: 40px;">
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif

        @if(!$profile || !$defaultAddress)
            <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-info-circle"></i>
                <span>
                    Complete your profile{{ !$profile && !$defaultAddress ? ' and address' : (!$defaultAddress ? "'s address" : '') }}
                    below to speed up future bookings.
                </span>
            </div>
        @endif

        <div class="row g-4">
            {{-- SIDEBAR: summary + tab nav --}}
            <div class="col-12 col-lg-4">
                <div class="nx-card p-4" style="position: sticky; top: 88px;">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="nx-avatar-circle">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="fw-semibold mb-0">{{ $user->name }}</p>
                            <p class="small nx-text-secondary mb-0">
                                Member since {{ $user->created_at->format('M Y') }}
                            </p>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold">Profile completion</span>
                        <span class="small nx-text-secondary">{{ $profileCompletion }}%</span>
                    </div>
                    <div class="nx-progress-track mb-4">
                        <div class="nx-progress-fill" style="width: {{ $profileCompletion }}%;"></div>
                    </div>

                    <div class="nav flex-column nx-profile-nav gap-1" role="tablist" aria-orientation="vertical">
                        <button class="{{ $activeProfileTab === 'profile-account' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#profile-account" type="button" role="tab" aria-selected="{{ $activeProfileTab === 'profile-account' ? 'true' : 'false' }}">
                            <i class="bi bi-shield-lock"></i>Account
                        </button>
                        <button class="{{ $activeProfileTab === 'profile-personal' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#profile-personal" type="button" role="tab" aria-selected="{{ $activeProfileTab === 'profile-personal' ? 'true' : 'false' }}">
                            <i class="bi bi-person-vcard"></i>Personal Information
                        </button>
                        <button class="{{ $activeProfileTab === 'profile-address' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#profile-address" type="button" role="tab" aria-selected="{{ $activeProfileTab === 'profile-address' ? 'true' : 'false' }}">
                            <i class="bi bi-geo-alt"></i>Address
                        </button>
                    </div>
                </div>
            </div>

            {{-- ONE big card, content swapped per tab --}}
            <div class="col-12 col-lg-8">
                <div class="nx-card p-4 p-sm-5">
                    <form method="POST" action="{{ route('account.profile.update') }}">
                        @csrf
                        @method('PATCH')

                        <div class="tab-content">
                            {{-- ACCOUNT --}}
                            <div class="tab-pane fade {{ $activeProfileTab === 'profile-account' ? 'show active' : '' }}" id="profile-account" role="tabpanel">
                                <div class="nx-profile-pane-heading">
                                    <div class="nx-profile-pane-icon"><i class="bi bi-shield-lock"></i></div>
                                    <div>
                                        <h2 class="h5 mb-0">Account Settings</h2>
                                        <p class="small nx-text-secondary mb-0">Manage your login email and password.</p>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                           class="form-control @error('email') is-invalid @enderror" required>
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <hr class="my-4" style="border-color: var(--nx-border);">
                                <p class="nx-eyebrow mb-3">Change Password <span class="nx-text-secondary" style="font-weight:400; letter-spacing:0; text-transform:none;">(optional)</span></p>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="password"
                                               class="form-control @error('password') is-invalid @enderror">
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @else
                                            <div class="form-text">Leave blank to keep your current password.</div>
                                        @enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" name="password_confirmation" class="form-control">
                                    </div>
                                </div>
                            </div>

                            {{-- PERSONAL --}}
                            <div class="tab-pane fade {{ $activeProfileTab === 'profile-personal' ? 'show active' : '' }}" id="profile-personal" role="tabpanel">
                                <div class="nx-profile-pane-heading">
                                    <div class="nx-profile-pane-icon"><i class="bi bi-person-vcard"></i></div>
                                    <div>
                                        <h2 class="h5 mb-0">Personal Information</h2>
                                        <p class="small nx-text-secondary mb-0">Tell us a bit about yourself.</p>
                                    </div>
                                </div>

                                <div class="row g-3 mb-1">
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="first_name" value="{{ old('first_name', $profile?->first_name ?? '') }}"
                                               class="form-control @error('first_name') is-invalid @enderror">
                                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">Middle Name <span class="nx-text-secondary" style="font-weight:400;">(Optional)</span></label>
                                        <input type="text" name="middle_name" value="{{ old('middle_name', $profile?->middle_name ?? '') }}"
                                               class="form-control @error('middle_name') is-invalid @enderror">
                                        @error('middle_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="last_name" value="{{ old('last_name', $profile?->last_name ?? '') }}"
                                               class="form-control @error('last_name') is-invalid @enderror">
                                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">Gender</label>
                                        @php($currentGender = old('gender', $profile?->gender?->value ?? ''))
                                        <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                                            <option value="" {{ $currentGender ? '' : 'selected' }}>Select...</option>
                                            @foreach(\App\Enums\Gender::cases() as $genderOption)
                                                <option value="{{ $genderOption->value }}" {{ $currentGender === $genderOption->value ? 'selected' : '' }}>
                                                    {{ $genderOption->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">Birthdate</label>
                                        <input type="date" name="birthdate" value="{{ old('birthdate', optional($profile?->birthdate)->toDateString()) }}"
                                               max="{{ now()->subDay()->toDateString() }}"
                                               class="form-control @error('birthdate') is-invalid @enderror">
                                        @error('birthdate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">Mobile Phone</label>
                                        <input type="tel" name="mobile_number" value="{{ old('mobile_number', $profile?->mobile_number ?? '') }}" placeholder="09XXXXXXXXX"
                                               class="form-control @error('mobile_number') is-invalid @enderror">
                                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            {{-- ADDRESS --}}
                            <div class="tab-pane fade {{ $activeProfileTab === 'profile-address' ? 'show active' : '' }}" id="profile-address" role="tabpanel">
                                <div class="nx-profile-pane-heading">
                                    <div class="nx-profile-pane-icon"><i class="bi bi-geo-alt"></i></div>
                                    <div>
                                        <h2 class="h5 mb-0">Address</h2>
                                        <p class="small nx-text-secondary mb-0">Where should we send Home Service visits?</p>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">House / Unit / Building / Street</label>
                                    <input type="text" name="street_address" value="{{ old('street_address', $defaultAddress?->street_address ?? '') }}"
                                           class="form-control @error('street_address') is-invalid @enderror">
                                    @error('street_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Barangay</label>
                                        <input type="text" name="barangay" value="{{ old('barangay', $defaultAddress?->barangay ?? '') }}"
                                               class="form-control @error('barangay') is-invalid @enderror">
                                        @error('barangay')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">City / Municipality</label>
                                        <input type="text" name="city_municipality" value="{{ old('city_municipality', $defaultAddress?->city_municipality ?? '') }}"
                                               class="form-control @error('city_municipality') is-invalid @enderror">
                                        @error('city_municipality')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Province</label>
                                        <input type="text" name="province" value="{{ old('province', $defaultAddress?->province ?? '') }}"
                                               class="form-control @error('province') is-invalid @enderror">
                                        @error('province')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Postal Code <span class="nx-text-secondary" style="font-weight:400;">(Optional)</span></label>
                                        <input type="text" name="postal_code" value="{{ old('postal_code', $defaultAddress?->postal_code ?? '') }}" inputmode="numeric"
                                               class="form-control @error('postal_code') is-invalid @enderror">
                                        @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4" style="border-color: var(--nx-border);">
                        <button class="nx-btn nx-btn-primary w-100 justify-content-center">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
