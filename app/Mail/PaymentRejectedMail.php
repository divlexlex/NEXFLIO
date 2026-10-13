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

        $lines[] = 'The booking has been cancelled. You are welcome to book again '
            . 'with an updated proof of payment — we would love to see you.';

        return new Content(view: 'mail.notification', with: [
            'title' => 'We could not verify your payment',
            'lines' => $lines,
        ]);
    }

    /**
     * Display label for the structured rejection category (see
     * config/payment.php 'rejection_reasons'). Null when the rejection
     * carries none (pre-category rejections, API cancels).
     */
    public function categoryLabel(): ?string
    {
        if (! $this->category) {
            return null;
        }

        return config('payment.rejection_reasons.'.$this->category.'.label', $this->category);
    }
}
