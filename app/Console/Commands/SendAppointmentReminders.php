<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:remind';

    protected $description = 'Send in-app, email, and push reminders for tomorrow\'s booked appointments';

    public function handle(NotificationService $notifications): int
    {
        $appointments = Appointment::with(['user', 'service', 'personnel'])
            ->where('status', AppointmentStatus::Booked)
            ->whereDate('appointment_date', now()->addDay()->toDateString())
            ->whereNotNull('user_id')
            ->get();

        foreach ($appointments as $appointment) {
            if ($appointment->user === null) {
                continue;
            }

            $notifications->notify(
                $appointment->user,
                'Appointment reminder',
                "Reminder: {$appointment->service->name} tomorrow at {$appointment->start_time}.",
                new AppointmentReminderMail($appointment),
                ['appointment_id' => $appointment->id]
            );
        }

        $this->info("Sent {$appointments->count()} reminder(s).");

        return self::SUCCESS;
    }
}
