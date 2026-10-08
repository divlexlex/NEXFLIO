<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RedirectsAfterAuth;
use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Single, shared Website login gateway. There is one `users` table and one
 * `web` session guard for every role (Super Admin/Manager/Staff/Client) —
 * this controller never chooses a role, it only reads the authenticated
 * user's existing `role_id` and decides where they land (see
 * RedirectsAfterAuth::destinationFor()). Real authorization still lives in
 * the `role:` middleware on the admin/staff/account routes (routes/web.php),
 * not here — this only controls a post-login redirect.
 */
class AuthController extends Controller
{
    use RedirectsAfterAuth;

    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return redirect()->to($this->destinationFor(Auth::user()));
        }

        // Guest "Book an Appointment" → "Continue on Website" links here with
        // ?redirect=, so a Client lands back where they started once logged
        // in — the same intended-URL session key Laravel's own auth
        // middleware uses. Management always goes to the dashboard regardless
        // (see destinationFor()), so this never affects that redirect.
        $redirect = $request->query('redirect');
        if ($this->isSafeRedirectPath($redirect)) {
            $request->session()->put('url.intended', $redirect);
        }

        return view('auth.login');
    }

    public function login(Request $request, EmailVerificationService $verification)
    {
        $validated = $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        // Accept email or username — look up the user manually so we can
        // match against either column, then verify the password ourselves.
        $user = User::where('email', $validated['email'])
            ->orWhere('username', $validated['email'])
            ->first();

        if (! $user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'No account found with that email or username. Please create an account or check your spelling.']);
        }

        if (! Hash::check($validated['password'], $user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['password' => 'Incorrect password. Please try again or use "Forgot password?" to reset it.']);
        }

        Auth::login($user, $request->boolean('remember'));

        $user = Auth::user();

        // Accounts created before email verification existed were
        // grandfathered in as verified (see the backfill migration), so this
        // only ever stops a genuinely new, unverified account.
        if (! $user->hasVerifiedEmail()) {
            Auth::logout();
            $request->session()->regenerateToken();
            $request->session()->put('verify_user_id', $user->id);
            $verification->issue($user);

            return redirect()->route('verification.show')
                ->with('status', 'Please verify your account first — we sent a new code to your email.');
        }

        $request->session()->regenerate();

        return redirect()->to($this->destinationFor($user));
    }

    public function showRegister(Request $request)
    {
        if (Auth::check()) {
            return redirect()->to($this->destinationFor(Auth::user()));
        }

        // Same intended-URL passthrough as showLogin(), so a Guest who lands
        // here from the Book Appointment modal's redirect param (via the
        // "Create an account" link on the login page) still returns to
        // where they started after registering.
        $redirect = $request->query('redirect');
        if ($this->isSafeRedirectPath($redirect)) {
            $request->session()->put('url.intended', $redirect);
        }

        return view('auth.register');
    }

    public function register(Request $request, EmailVerificationService $verification)
    {
        // Website registration collects only the essentials (name, email,
        // mobile, password) plus a Terms acceptance — gender/birthdate/
        // address are no longer asked here and are instead filled in later
        // from the Account > Profile page (ClientAccountController@updateProfile
        // already treats all of those as optional). This deliberately
        // diverges from App\Http\Controllers\API\AuthController@register
        // (mobile), which is left untouched — out of scope for this
        // Website-only task. Both create an identical `users` row (role_id
        // forced to Client server-side, never from the request) either way.
        $fields = $request->validate([
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'email' => 'required|string|email',
            // 09XXXXXXXXX or +639XXXXXXXXX — accepts both common PH mobile
            // formats without being stricter than that (no carrier-prefix
            // allowlist, no re-formatting of what the Client typed).
            'mobile_number' => ['required', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'password' => 'required|string|min:8|confirmed',
            'terms' => 'accepted',
        ], [
            'terms.accepted' => 'You must agree to the Terms & Conditions to create an account.',
        ]);

        // The email uniqueness check is done here (not via `unique:users,email`
        // above) so a half-finished registration — account created but email
        // not verified yet — can resume the verification flow instead of being
        // stuck behind a dead-end "The email has already been taken." error.
        // withTrashed() matters: a soft-deleted account still owns the unique
        // email, so it has to be treated as taken rather than re-created.
        $existing = User::withTrashed()->where('email', $fields['email'])->first();

        if ($existing && $existing->trashed()) {
            return back()
                ->withInput($request->only(['last_name', 'first_name', 'middle_name', 'mobile_number', 'email']))
                ->withErrors(['email' => 'This email is already registered. Contact support if you want it restored.']);
        }

        if ($existing && $existing->hasVerifiedEmail()) {
            return back()
                ->withInput($request->only(['last_name', 'first_name', 'middle_name', 'mobile_number', 'email']))
                ->withErrors(['email' => 'This email is already registered. Please sign in instead.']);
        }

        if ($existing) {
            // Unverified account already exists (e.g. they registered moments
            // ago but never got/finished the code). Resume that same flow —
            // resend the code and let them prove the email, never error out.
            $request->session()->put('verify_user_id', $existing->id);
            $verification->issue($existing);

            return redirect()->route('verification.show')
                ->with('status', 'Account already exists but is not verified yet. We sent a new verification code to your email.');
        }

        $user = DB::transaction(function () use ($fields) {
            $fullName = trim(preg_replace(
                '/\s+/',
                ' ',
                "{$fields['first_name']} ".($fields['middle_name'] ?? '')." {$fields['last_name']}"
            ));

            $user = User::create([
                'name' => $fullName,
                'email' => $fields['email'],
                'password' => Hash::make($fields['password']),
                'role_id' => User::ROLE_CLIENT,
            ]);

            $user->clientProfile()->create([
                'first_name' => $fields['first_name'],
                'middle_name' => $fields['middle_name'] ?? null,
                'last_name' => $fields['last_name'],
                'mobile_number' => $fields['mobile_number'],
            ]);

            return $user;
        });

        // Not logged in yet — the account only gets full access once the
        // Client proves they control this email (see EmailVerificationController).
        $request->session()->put('verify_user_id', $user->id);
        $verification->issue($user);

        return redirect()->route('verification.show')
            ->with('status', 'We sent a 6-digit verification code to your email.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }

    private function isSafeRedirectPath(?string $path): bool
    {
        if (! $path || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return false;
        }

        return parse_url($path, PHP_URL_HOST) === null;
    }
}
