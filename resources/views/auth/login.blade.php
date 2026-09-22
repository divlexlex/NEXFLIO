@extends('layouts.public')

@section('title', 'Sign In')

@section('content')
<div class="container py-5 d-flex align-items-center" style="min-height: calc(100vh - 80px);">
    <div class="row justify-content-center w-100">
        <div class="col-md-5 col-lg-4">
            <div class="nx-card p-4">
                <h1 class="h3 text-center mb-1">Sign In</h1>
                <p class="text-center nx-text-secondary small mb-4">
                    Sign in to continue to your Perfect Nails account.
                </p>

                @if(session('status'))
                    <div class="alert alert-success small">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login.attempt') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email or Username</label>
                        <input type="text" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" required autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember">
                            <label class="form-check-label small" for="remember">Keep me signed in</label>
                        </div>
                        <a href="{{ route('password.request') }}" class="nx-text-accent small">Forgot password?</a>
                    </div>
                    <button class="nx-btn nx-btn-primary w-100 py-2 justify-content-center">Sign In</button>
                </form>

                <p class="text-center small nx-text-secondary mt-4 mb-0">
                    New to Perfect Nails?
                    <a href="{{ route('register', request()->query('redirect') ? ['redirect' => request()->query('redirect')] : []) }}" class="nx-text-accent">Create an account</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
