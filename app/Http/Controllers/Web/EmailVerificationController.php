<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RedirectsAfterAuth;
use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The "enter the 6-digit code" step both AuthController@register and
 * AuthController@login redirect into when an account isn't verified yet.
 * The Client isn't authenticated during this step — who they are is tracked
 * via a plain `verify_user_id` session key (the same session-based handoff
 * pattern PasswordResetController's token/email pair already uses), and
 * Auth::login() only happens once the code checks out.
 */
class EmailVerificationController extends Controller
{
    use RedirectsAfterAuth;

    public function show(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.verify-email');
    }

    public function verify(Request $request, EmailVerificationService $verification)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $result = $verification->attempt($user, $request->string('code'));

        if ($result !== 'verified') {
            $messages = [
                'invalid' => 'Incorrect code. Please try again.',
                'expired' => 'That code has expired. Please request a new one.',
                'too_many_attempts' => 'Too many incorrect attempts. Please request a new code.',
                'no_pending_code' => 'Please request a verification code first.',
            ];

            return back()->withErrors(['code' => $messages[$result]]);
        }

        $request->session()->forget('verify_user_id');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to($this->destinationFor($user));
    }

    public function resend(Request $request, EmailVerificationService $verification)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $key = 'verify-resend:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors(['code' => 'Please wait a bit before requesting another code.']);
        }
        RateLimiter::hit($key, 60);

        $verification->issue($user);

        return back()->with('status', 'A new code has been sent to your email.');
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('verify_user_id');

        return $id ? User::find($id) : null;
    }
}
