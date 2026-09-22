<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Deliberately NOT ShouldQueue (unlike the other App\Mail\*Mail classes) —
 * the recipient is sitting on the Verify Your Account screen waiting for
 * this code, so it must send synchronously instead of depending on a queue
 * worker being up.
 */
class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code, public readonly string $recipientName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Perfect Nails verification code');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.notification', with: [
            'title' => 'Verify your account',
            'lines' => [
                "Hi {$this->recipientName},",
                'Your verification code is:',
                $this->code,
                'This code expires in 10 minutes.',
                "If you didn't try to create or sign in to a Perfect Nails account, you can safely ignore this email.",
            ],
        ]);
    }
}
