<?php

namespace App\Mail;

use App\Enums\AppointmentStatus;
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
        $timeLabel = $this->appointment->startTimeLabel();

        $price = (float) ($this->appointment->booked_price ?? $this->appointment->service?->price ?? 0);
        $paidNow = (float) ($this->appointment->payment?->amount ?? 0);

        // Cash bookings are confirmed at once — the whole price is payable
        // at the branch. Online bookings that only paid a down payment still
        // owe the balance at the branch.
        if ($this->appointment->status === AppointmentStatus::Booked
            && $this->appointment->payment?->method === 'cash') {
            return new Content(view: 'mail.notification', with: [
                'title' => 'Your booking is confirmed!',
                'lines' => [
                    "Hi {$this->appointment->clientName()},",
                    "Thank you for booking {$service} on "
                        . $this->appointment->appointment_date->format('F j, Y')
                        . " at {$timeLabel}.",
                    'Your booking is confirmed — please pay ₱'.number_format($price, 2)
                        . ' at the branch.',
                ],
            ]);
        }

        if ($paidNow < $price) {
            return new Content(view: 'mail.notification', with: [
                'title' => 'We received your booking!',
                'lines' => [
                    "Hi {$this->appointment->clientName()},",
                    "Thank you for booking {$service} on "
                        . $this->appointment->appointment_date->format('F j, Y')
                        . " at {$timeLabel}.",
                    'Our manager is verifying your down payment of ₱'.number_format($paidNow, 2)
                        . '. The remaining ₱'.number_format($price - $paidNow, 2)
                        . ' is payable at the branch.',
                ],
            ]);
        }

        return new Content(view: 'mail.notification', with: [
            'title' => 'We received your booking!',
            'lines' => [
                "Hi {$this->appointment->clientName()},",
                "Thank you for booking {$service} on "
                    . $this->appointment->appointment_date->format('F j, Y')
                    . " at {$timeLabel}.",
                'Our manager is verifying your proof of payment. You will get another '
                    . 'notification the moment your booking is confirmed.',
            ],
        ]);
    }
}
