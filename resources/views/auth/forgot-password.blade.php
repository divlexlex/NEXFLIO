@extends('layouts.public')

@section('title', 'Forgot Password')

@section('content')
<div class="container py-5 d-flex align-items-center" style="min-height: calc(100vh - 80px);">
    <div class="row justify-content-center w-100">
        <div class="col-md-5 col-lg-4">
            <div class="nx-card p-4">
                <h1 class="h3 text-center mb-1">Forgot Password</h1>
                <p class="text-center nx-text-secondary small mb-4">
                    Enter the email on your account and we'll send you a link to reset your password.
                </p>

                @if(session('status'))
                    <div class="alert alert-success small">
                        {{ session('status') }}
                    </div>
                    <div class="alert alert-info small">
                        <i class="bi bi-envelope-exclamation me-1"></i>
                        <strong>Tip:</strong> The reset link may take a few minutes. If you don't see it, check your
                        <strong>Spam</strong> or <strong>Junk</strong> folder.
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" required autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button class="nx-btn nx-btn-primary w-100 py-2 justify-content-center">Send Reset Link</button>
                </form>

                <p class="text-center small nx-text-secondary mt-4 mb-0">
                    <a href="{{ route('login') }}" class="nx-text-accent">Back to Sign In</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
