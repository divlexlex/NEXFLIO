<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the client their refund for a rejected booking has been sent back.
 * Dispatched from PaymentController@markRefundSent (step 2A refund flow).
 */
class RefundSentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Payment $payment)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your refund has been sent — Perfect Nails');
    }

    public function content(): Content
    {
        $appointment = $this->payment->appointment;
        $amount = number_format((float) ($this->payment->amount_received ?? 0), 2);
        $code = $appointment?->referenceCode() ?? 'your booking';

        return new Content(view: 'mail.notification', with: [
            'title' => 'Your refund is on its way',
            'lines' => [
                "Hi {$appointment?->clientName()},",
                "Your ₱{$amount} refund for booking {$code} has been sent back "
                    .'via your payment method. Please allow a few days for it '
                    .'to appear, and message us if it does not arrive.',
            ],
        ]);
    }
}
