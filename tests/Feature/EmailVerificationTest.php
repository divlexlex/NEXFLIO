<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_registering_does_not_log_in_and_sends_a_code(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'last_name' => 'Cruz',
            'first_name' => 'Juan',
            'email' => 'juan.verify@example.com',
            'mobile_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('verification.show'));
        $this->assertGuest();

        $user = User::where('email', 'juan.verify@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('verification_codes', ['user_id' => $user->id]);

        Mail::assertSent(VerificationCodeMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_verify_page_redirects_to_login_without_a_pending_registration(): void
    {
        $this->get('/verify-email')->assertRedirect(route('login'));
    }

    public function test_correct_code_verifies_and_logs_the_user_in(): void
    {
        $user = $this->registerAndCaptureCode('ana.verify@example.com', $code);

        $response = $this->post('/verify-email', ['code' => $code]);

        $response->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_wrong_code_is_rejected_without_verifying(): void
    {
        $user = $this->registerAndCaptureCode('liza.verify@example.com', $code);
        $wrongCode = $code === '000000' ? '111111' : '000000';

        $response = $this->post('/verify-email', ['code' => $wrongCode]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_too_many_wrong_attempts_requires_a_resend(): void
    {
        $user = $this->registerAndCaptureCode('mark.verify@example.com', $code);
        $wrongCode = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/verify-email', ['code' => $wrongCode]);
        }

        $response = $this->post('/verify-email', ['code' => $wrongCode]);
        $response->assertSessionHasErrors('code');
        $this->assertStringContainsString('Too many', session('errors')->first('code'));

        // Even the correct code no longer works — a resend is required.
        $response = $this->post('/verify-email', ['code' => $code]);
        $response->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_issues_a_new_code_and_is_rate_limited(): void
    {
        Mail::fake();

        $this->post('/register', [
            'last_name' => 'Dela Cruz',
            'first_name' => 'Paolo',
            'email' => 'paolo.verify@example.com',
            'mobile_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ]);

        $user = User::where('email', 'paolo.verify@example.com')->firstOrFail();

        $first = $this->post('/verify-email/resend');
        $first->assertSessionHas('status');
        Mail::assertSent(VerificationCodeMail::class, 2);

        $second = $this->post('/verify-email/resend');
        $second->assertSessionHasErrors('code');
        Mail::assertSent(VerificationCodeMail::class, 2);

        $this->assertSame(1, $user->verificationCodes()->whereNull('consumed_at')->count());
    }

    public function test_login_blocks_an_unverified_existing_account_and_sends_a_new_code(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Unverified Client',
            'email' => 'unverified.client@example.com',
            'password' => Hash::make('password'),
            'role_id' => User::ROLE_CLIENT,
            'email_verified_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('verification.show'));
        $this->assertGuest();
        Mail::assertSent(VerificationCodeMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_login_still_works_for_already_verified_accounts(): void
    {
        $client = User::where('email', 'client@nexflio.test')->firstOrFail();

        $this->post('/login', [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect(route('account.dashboard'));

        $this->assertAuthenticatedAs($client);
    }

    /**
     * Registers a fresh Client and captures the plaintext code from the
     * faked mail (the DB only ever stores it hashed) via a by-ref out param,
     * since PHPUnit test methods can't return a tuple cleanly.
     */
    private function registerAndCaptureCode(string $email, ?string &$code): User
    {
        Mail::fake();

        $this->post('/register', [
            'last_name' => 'Test',
            'first_name' => 'User',
            'email' => $email,
            'mobile_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ]);

        $user = User::where('email', $email)->firstOrFail();

        $captured = null;
        Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$captured) {
            $captured = $mail->code;

            return true;
        });

        $code = $captured;

        $this->assertNotNull(VerificationCode::where('user_id', $user->id)->first());

        return $user;
    }
}
