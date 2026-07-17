<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentVerifiedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your booking is confirmed — Perfect Nails');
    }

    public function content(): Content
    {
        $service = $this->appointment->service->name ?? 'your service';

        return new Content(view: 'mail.notification', with: [
            'title' => 'Your booking is confirmed!',
            'lines' => [
                "Hi {$this->appointment->clientName()},",
                "Your payment has been verified and {$service} is booked for "
                    . $this->appointment->appointment_date->format('F j, Y')
                    . " at {$this->appointment->start_time}"
                    . ($this->appointment->personnel ? " with {$this->appointment->personnel->name}." : '.'),
                'See you at the spa! If you need to make changes, please contact us ahead of time.',
            ],
        ]);
    }
}
