<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Appointment ID (reference_code) display — Task B.
 *
 * The same code (PN-YYYYMMDD-<id 6-padded>) must appear on the booking
 * success page, My Bookings, the admin calendar event title, and the
 * details drawer (via feed extendedProps). Fee-run behaviour (Cash lands
 * Booked, fee breakdowns) must be unchanged.
 */
class AppointmentReferenceCodeTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private Service $manicure;

    private Service $massage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $this->manicure = Service::create([
            'name' => 'Manicure (test)', 'category' => 'Nails', 'description' => 'Test',
            'price' => 179, 'down_payment' => null, 'duration_minutes' => 45, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
        $this->massage = Service::create([
            'name' => 'Swedish Massage (test)', 'category' => 'Massage', 'description' => 'Test',
            'price' => 599, 'down_payment' => 300, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function branchPayload(array $overrides = []): array
    {
        return array_merge([
            'service_id' => $this->manicure->id,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            // 13:00 is inside both the weekday (12:00-22:00) and weekend
            // (10:00-22:00) windows, so "tomorrow" is valid any day.
            'start_time' => '13:00',
            'method' => 'gcash',
            'guests' => 1,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ], $overrides);
    }

    private function expectedCode(Appointment $appointment): string
    {
        return sprintf(
            'PN-%s-%06d',
            $appointment->created_at->format('Ymd'),
            $appointment->id
        );
    }

    private function feedEvent(int $appointmentId): array
    {
        $response = $this->actingAs($this->manager)->getJson(route('admin.appointments.feed'));

        $response->assertOk();

        foreach ($response->json() as $event) {
            if ((int) $event['id'] === $appointmentId) {
                return $event;
            }
        }

        $this->fail("Appointment {$appointmentId} missing from calendar feed.");
    }

    // Branch booking end-to-end: success page, My Bookings, calendar title
    // and drawer props all carry the SAME persisted ID in the right format.
    public function test_branch_booking_shows_same_id_everywhere(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload()
        );
        $response->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $code = $this->expectedCode($appointment);

        $this->assertSame($code, $appointment->reference_code);
        $this->assertSame($code, $appointment->referenceCode());
        $this->assertMatchesRegularExpression('/^PN-\d{8}-\d{6}$/', $code);

        // Success page: prominent ID + spa copy, fee-run copy intact.
        $this->actingAs($this->client)->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee($code)
            ->assertSee('Show this ID at the spa so we can confirm your booking.');

        // My Bookings card carries the ID next to the payment state.
        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee($code);

        // Admin calendar: prefixed title + extendedProps for the drawer.
        $event = $this->feedEvent($appointment->id);
        $this->assertSame(
            "{$code} · {$this->client->name} · {$this->manicure->name}",
            $event['title']
        );
        $this->assertSame($code, $event['extendedProps']['reference_code']);
    }

    // Cash booking: ID present, Unverified request state (awaiting
    // confirmation), payable-at-spa copy on the success page.
    public function test_cash_booking_shows_id_and_awaits_confirmation(): void
    {
        $response = $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['method' => 'cash', 'payment_proof' => null])
        );
        $response->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $code = $this->expectedCode($appointment);

        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertSame('Awaiting confirmation — full amount payable at the spa', $appointment->paymentState());

        $this->actingAs($this->client)->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Booking Request Submitted')
            ->assertDontSee('Booking Confirmed')
            ->assertSee($code)
            ->assertSee('Payable at the branch');

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee($code);
    }

    // Fee booking (Massage): breakdown intact and the ID present.
    public function test_fee_booking_keeps_breakdown_and_shows_id(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->massage->id, 'guests' => 2])
        );
        $response->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(300, (float) $appointment->payment->amount);

        $this->actingAs($this->client)->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee($this->expectedCode($appointment))
            ->assertSee('Down payment (paid online)')
            ->assertSee('Balance (pay at the branch)');
    }

    // Admin walk-in receives an ID, visible to the admin feed/drawer.
    public function test_walk_in_receives_id(): void
    {
        $staff = User::create([
            'name' => 'Walk-in Staff', 'email' => 'walkin-staff@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_STAFF,
        ]);
        StaffProfile::create([
            'user_id' => $staff->id,
            'base_pay' => 12000,
            'commission_rate' => 10,
            'position' => 'Nail Technician',
            'employment_status' => 'active',
            'hired_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Walk-in Client',
            'service_id' => $this->manicure->id,
            'personnel_id' => $staff->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '15:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::latest('id')->firstOrFail();
        $code = $this->expectedCode($appointment);
        $this->assertSame($code, $appointment->reference_code);

        $event = $this->feedEvent($appointment->id);
        $this->assertStringStartsWith($code.' · ', $event['title']);
        $this->assertSame($code, $event['extendedProps']['reference_code']);
    }

    // Rescheduling (override) never changes the ID.
    public function test_reschedule_keeps_id(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload()
        );
        $appointment = Appointment::latest('id')->firstOrFail();
        $code = $appointment->reference_code;
        $this->assertNotNull($code);

        $staff = User::create([
            'name' => 'Override Staff', 'email' => 'override-staff@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_STAFF,
        ]);
        $this->actingAs($this->manager)->patch(
            route('admin.appointments.override', $appointment->id),
            [
                'appointment_date' => now()->addDays(2)->toDateString(),
                'start_time' => '14:00',
                'personnel_id' => $staff->id,
            ]
        )->assertRedirect();

        $this->assertSame($code, $appointment->refresh()->reference_code);
    }
}
