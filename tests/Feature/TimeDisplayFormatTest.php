<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\AppointmentReminderMail;
use App\Mail\BookingReceivedMail;
use App\Mail\PaymentVerifiedMail;
use App\Models\Appointment;
use App\Models\BusinessHours;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every time a user reads as text must be 12-hour ("2:00 PM"), never military
 * ("14:00"). Display-only sweep across the client pages, the booking wizard,
 * and the notification mail.
 */
class TimeDisplayFormatTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private User $staff;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $this->staff = User::create([
            'name' => 'Tina Twelve',
            'email' => 'tina.twelve@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
        ]);
        StaffProfile::create([
            'user_id' => $this->staff->id,
            'position' => 'Nail Technician',
            'employment_status' => 'active',
        ]);

        $this->service = Service::create([
            'name' => 'Twelve Hour Manicure (test)', 'category' => 'Nails', 'description' => 'Test',
            'price' => 749, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        foreach (range(0, 6) as $day) {
            BusinessHours::updateOrCreate(
                ['day_of_week' => $day],
                ['is_open' => true, 'open_at' => '10:00', 'close_at' => '21:00']
            );
        }
    }

    private function makeAppointment(AppointmentStatus $status, string $start = '14:00:00'): Appointment
    {
        return Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDays(3)->toDateString(),
            'start_time' => $start,
            'status' => $status,
            'booked_price' => 749,
        ]);
    }

    public function test_my_bookings_card_shows_twelve_hour_time(): void
    {
        $this->makeAppointment(AppointmentStatus::Booked);

        $html = $this->actingAs($this->client)
            ->get(route('account.bookings'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('2:00 PM', $html);
        $this->assertStringNotContainsString('14:00', $html);
    }

    public function test_receipt_shows_twelve_hour_time(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Completed);
        Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => 300, 'method' => 'gcash', 'kind' => 'payment',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id, 'verified_at' => now(),
        ]);

        $html = $this->actingAs($this->client)
            ->get(route('account.bookings.receipt', $appointment->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('2:00 PM', $html);
        $this->assertStringNotContainsString('14:00', $html);
    }

    public function test_booking_wizard_slots_use_twelve_hour_labels(): void
    {
        $date = now()->addDays(3)->toDateString();

        $html = $this->actingAs($this->client)
            ->get(route('account.booking.branch.schedule', [
                'service' => $this->service->id,
                'date' => $date,
                'personnel' => (string) $this->staff->id,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('2:00 PM', $html);
        $this->assertStringContainsString('data-slot-time="14:00"', $html);
        $this->assertStringNotContainsString('>14:00<', $html);
    }

    public function test_notification_mails_use_twelve_hour_time(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Booked)->load('service', 'personnel');

        foreach ([
            new BookingReceivedMail($appointment),
            new PaymentVerifiedMail($appointment),
            new AppointmentReminderMail($appointment),
        ] as $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('2:00 PM', $html);
            $this->assertStringNotContainsString('14:00', $html);
        }
    }
}
