<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Issues and checks the 6-digit codes used to prove a Client actually
 * controls the email/mobile they registered with, before their account gets
 * full access. `channel` is stored per-code so an SMS delivery path can be
 * added later (see the verification_codes migration) without touching this
 * class's callers — only issue()'s delivery step would change.
 */
class EmailVerificationService
{
    private const CODE_LENGTH = 6;

    private const EXPIRES_IN_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function issue(User $user): void
    {
        // Invalidate any still-pending code so only the latest one a Client
        // was actually emailed can ever be accepted.
        $user->verificationCodes()->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = (string) random_int(10 ** (self::CODE_LENGTH - 1), (10 ** self::CODE_LENGTH) - 1);

        $user->verificationCodes()->create([
            'code' => Hash::make($code),
            'channel' => 'email',
            'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
        ]);

        Mail::to($user->email)->send(new VerificationCodeMail($code, $user->name));
    }

    /**
     * @return 'verified'|'invalid'|'expired'|'too_many_attempts'|'no_pending_code'
     */
    public function attempt(User $user, string $code): string
    {
        $record = $user->verificationCodes()->whereNull('consumed_at')->latest('id')->first();

        if (! $record) {
            return 'no_pending_code';
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            return 'too_many_attempts';
        }

        if ($record->expires_at->isPast()) {
            return 'expired';
        }

        if (! Hash::check($code, $record->code)) {
            $record->increment('attempts');

            return $record->attempts >= self::MAX_ATTEMPTS ? 'too_many_attempts' : 'invalid';
        }

        $record->update(['consumed_at' => now()]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return 'verified';
    }
}
