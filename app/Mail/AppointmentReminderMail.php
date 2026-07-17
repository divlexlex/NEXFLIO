<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reminder: your appointment tomorrow — Perfect Nails');
    }

    public function content(): Content
    {
        $service = $this->appointment->service->name ?? 'your service';

        return new Content(view: 'mail.notification', with: [
            'title' => 'See you tomorrow!',
            'lines' => [
                "Hi {$this->appointment->clientName()},",
                "This is a friendly reminder of your {$service} appointment tomorrow, "
                    . $this->appointment->appointment_date->format('F j, Y')
                    . " at {$this->appointment->start_time}"
                    . ($this->appointment->personnel ? " with {$this->appointment->personnel->name}." : '.'),
                'Please arrive a few minutes early so we can start on time.',
            ],
        ]);
    }
}
