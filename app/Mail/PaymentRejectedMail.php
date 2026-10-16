<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly ?string $reason = null,
        public readonly ?string $category = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'About your booking payment — Perfect Nails');
    }

    public function content(): Content
    {
        $service = $this->appointment->service->name ?? 'your service';

        $lines = [
            "Hi {$this->appointment->clientName()},",
            "Unfortunately we could not verify the payment for your {$service} booking on "
                . $this->appointment->appointment_date->format('F j, Y') . '.',
        ];

        if ($this->categoryLabel() !== null) {
            $lines[] = 'Payment rejected — '.$this->categoryLabel()
                .($this->reason ? ": {$this->reason}" : '');
        } elseif ($this->reason) {
            $lines[] = "Reason: {$this->reason}";
        }

        $lines[] = 'The booking has been cancelled. '.$this->nextStepLine();

        return new Content(view: 'mail.notification', with: [
            'title' => 'We could not verify your payment',
            'lines' => $lines,
        ]);
    }

    /**
     * The client's actual next step for this rejection category — never a
     * refund promise (rejected-booking refunds no longer exist).
     */
    private function nextStepLine(): string
    {
        return match ($this->category) {
            'insufficient_amount' => 'You can still complete your booking by topping up the remaining fee from My Bookings.',
            'invalid_proof' => 'You can send a clearer photo or screenshot of your proof from My Bookings and we will review it again.',
            default => 'This decision is final — you are welcome to make a fresh booking any time.',
        };
    }

    /**
     * Display label for the structured rejection category (see
     * config/payment.php 'rejection_reasons'). Null when the rejection
     * carries none (pre-category rejections, API cancels). A stored key
     * missing from config falls back to a prettified key so historical
     * rows still read sensibly.
     */
    public function categoryLabel(): ?string
    {
        if (! $this->category) {
            return null;
        }

        return config(
            'payment.rejection_reasons.'.$this->category.'.label',
            ucfirst(str_replace('_', ' ', $this->category))
        );
    }
}
