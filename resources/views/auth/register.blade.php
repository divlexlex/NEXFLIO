{{-- Website Client registration. Deliberately short: name, email, mobile,
     password, and a Terms acceptance — nothing else. Gender/birthdate/address
     are collected later from Account > Profile (all optional there), not
     during sign-up. Posts to AuthController@register, which is the
     authoritative source of the validation rules; this form mirrors them for
     a good first-pass experience only. --}}
@extends('layouts.public')

@section('title', 'Create Account')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="nx-card p-4 p-md-5 mt-4">
                <h1 class="h3 text-center mb-1">Create Account</h1>
                <p class="text-center nx-text-secondary small mb-4">
                    Join Perfect Nails to manage your bookings online.
                </p>

                <form method="POST" action="{{ route('register.attempt') }}">
                    @csrf

                    <div class="row g-3 mb-1">
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}"
                                   class="form-control @error('last_name') is-invalid @enderror" required autofocus>
                            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}"
                                   class="form-control @error('first_name') is-invalid @enderror" required>
                            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Middle Name <span class="nx-text-secondary" style="font-weight:400;">(Optional)</span></label>
                            <input type="text" name="middle_name" value="{{ old('middle_name') }}"
                                   class="form-control @error('middle_name') is-invalid @enderror">
                            @error('middle_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile Number</label>
                            <input type="tel" name="mobile_number" value="{{ old('mobile_number') }}" placeholder="09XXXXXXXXX"
                                   class="form-control @error('mobile_number') is-invalid @enderror" required>
                            @error('mobile_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <div class="form-text">e.g. 09171234567 or +639171234567</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-1 mt-2">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-1 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <div class="form-text">At least 8 characters.</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-check mt-4 mb-4">
                        <input type="checkbox" name="terms" value="1" id="termsCheck"
                               class="form-check-input @error('terms') is-invalid @enderror" required>
                        <label class="form-check-label small" for="termsCheck">
                            I have read and agree to the
                            <a href="#" class="nx-text-accent" data-bs-toggle="modal" data-bs-target="#termsModal">Terms &amp; Conditions</a>.
                        </label>
                        @error('terms')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <button class="nx-btn nx-btn-primary w-100 py-2 justify-content-center">Create Account</button>
                </form>

                <p class="text-center small nx-text-secondary mt-4 mb-0">
                    Already have an account?
                    <a href="{{ route('login', request()->query('redirect') ? ['redirect' => request()->query('redirect')] : []) }}" class="nx-text-accent">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms &amp; Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body small">
                <p>By creating a Perfect Nails account, you agree to the following:</p>
                <ul>
                    <li>The information you provide (name, email, mobile number) is accurate and used to manage your bookings, send appointment confirmations, and contact you about your visits.</li>
                    <li>Appointments are subject to our booking, cancellation, and rescheduling policies as shown at checkout and in your account.</li>
                    <li>Your account is personal to you; please keep your password confidential.</li>
                    <li>We do not sell your personal information to third parties.</li>
                </ul>
                <p class="text-muted mb-0">This is a summary policy for Perfect Nails Wellness &amp; Aesthetics clients and may be updated from time to time.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection
