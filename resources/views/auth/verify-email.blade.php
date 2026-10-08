@extends('layouts.public')

@section('title', 'Verify Your Account')

@section('content')
<div class="container py-5 d-flex align-items-center" style="min-height: calc(100vh - 80px);">
    <div class="row justify-content-center w-100">
        <div class="col-md-5 col-lg-4">
            <div class="nx-card p-4">
                <h1 class="h3 text-center mb-4">Verify Your Account</h1>

                @if(session('status'))
                    <div class="alert alert-success small">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('verification.verify') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Verification Code</label>
                        <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                               autocomplete="one-time-code"
                               class="form-control text-center @error('code') is-invalid @enderror"
                               style="letter-spacing: .5em; font-size: 1.25rem;" required autofocus>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button class="nx-btn nx-btn-primary w-100 py-2 justify-content-center">Verify</button>
                </form>

                <form method="POST" action="{{ route('verification.resend') }}" class="text-center mt-4 mb-0">
                    @csrf
                    <span class="small nx-text-secondary">Didn't get the code?</span>
                    <button type="submit" class="btn btn-link btn-sm nx-text-accent p-0 align-baseline">Resend code</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
