@extends('layouts.public')

@section('title', 'Management Login')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card-spa p-4 mt-5">
                <h1 class="h3 text-center mb-1">Management Portal</h1>
                <p class="text-center text-muted small mb-4">
                    Owner &amp; Manager access only. Staff and clients use the mobile app.
                </p>

                <form method="POST" action="{{ route('login.attempt') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" required autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small" for="remember">Keep me signed in</label>
                    </div>
                    <button class="btn btn-spa w-100 py-2">Sign In</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
