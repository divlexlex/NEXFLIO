<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'We received your booking — Perfect Nails');
    }

    public function content(): Content
    {
        $service = $this->appointment->service->name ?? 'your service';

        return new Content(view: 'mail.notification', with: [
            'title' => 'We received your booking!',
            'lines' => [
                "Hi {$this->appointment->clientName()},",
                "Thank you for booking {$service} on "
                    . $this->appointment->appointment_date->format('F j, Y')
                    . " at {$this->appointment->start_time}.",
                'Our manager is verifying your proof of payment. You will get another '
                    . 'notification the moment your booking is confirmed.',
            ],
        ]);
    }
}
