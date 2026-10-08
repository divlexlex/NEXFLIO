@extends('layouts.public')

@section('title', 'Reset Password')

@section('content')
<div class="container py-5 d-flex align-items-center" style="min-height: calc(100vh - 80px);">
    <div class="row justify-content-center w-100">
        <div class="col-md-5 col-lg-4">
            <div class="nx-card p-4">
                <h1 class="h3 text-center mb-1">Reset Password</h1>
                <p class="text-center nx-text-secondary small mb-4">
                    Choose a new password for your Perfect Nails account.
                </p>

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email', $email) }}"
                               class="form-control @error('email') is-invalid @enderror" required autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="password-toggle-wrap">
                            <input type="password" name="password" id="reset-password"
                                   autocomplete="new-password"
                                   data-password-field="reset-password"
                                   class="form-control @error('password') is-invalid @enderror" required>
                            <button type="button" class="password-toggle" data-password-toggle tabindex="-1" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        @if($errors->has('password'))
                            <div class="invalid-feedback d-block">{{ $errors->first('password') }}</div>
                        @else
                            <div class="form-text">At least 8 characters.</div>
                        @endif
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <div class="password-toggle-wrap">
                            <input type="password" name="password_confirmation" id="reset-password-confirm"
                                   autocomplete="new-password"
                                   data-password-field="reset-password-confirm"
                                   class="form-control @error('password') is-invalid @enderror" required>
                            <button type="button" class="password-toggle" data-password-toggle tabindex="-1" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button class="nx-btn nx-btn-primary w-100 py-2 justify-content-center">Reset Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
