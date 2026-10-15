<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Digital transaction record ("receipt") for Completed appointments —
 * shared client + admin views aggregating Verified payments only.
 */
class AppointmentReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $otherClient;

    private User $manager;

    private User $staff;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        $this->otherClient = User::create([
            'name' => 'Other Client',
            'email' => 'other.client@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);

        $this->service = Service::create([
            'name' => 'Receipt Massage (test)', 'category' => 'Massage', 'description' => 'Test',
            'price' => 749, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function makeAppointment(User $client, AppointmentStatus $status = AppointmentStatus::Completed, array $overrides = []): Appointment
    {
        return Appointment::create(array_merge([
            'user_id' => $client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->subDay()->toDateString(),
            'start_time' => '10:00:00',
            'status' => $status,
            'booked_price' => 749,
        ], $overrides));
    }

    private function makePayment(Appointment $appointment, float $amount, string $method = 'gcash', ?string $kind = 'payment'): Payment
    {
        return Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => $amount,
            'method' => $method,
            'kind' => $kind,
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);
    }

    public function test_client_views_own_completed_receipt(): void
    {
        $appointment = $this->makeAppointment($this->client, AppointmentStatus::Completed, ['variant_label' => 'Couple']);
        $this->makePayment($appointment, 300, 'gcash');
        $this->makePayment($appointment, 100, 'gcash', 'topup');
        $this->makePayment($appointment, 50, 'gcash', 'partial_receipt');
        $this->makePayment($appointment, 299, 'cash', 'branch');

        $html = $this->actingAs($this->client)
            ->get(route('account.bookings.receipt', $appointment->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($appointment->referenceCode(), $html);
        $this->assertStringContainsString('Receipt Massage (test) — Couple', $html);
        $this->assertStringContainsString('Down payment — GCash', $html);
        $this->assertStringContainsString('₱450.00', $html);
        $this->assertStringContainsString('Paid at branch — Cash', $html);
        $this->assertStringContainsString('₱299.00', $html);

        // Total is the last money line on the page.
        $totalPos = strpos($html, 'Total amount');
        $this->assertNotFalse($totalPos);
        $this->assertStringContainsString('₱749.00', $html);
        $this->assertGreaterThan(strpos($html, '₱299.00'), $totalPos);

        $this->assertStringContainsString(
            'System-generated transaction record. Not a BIR-accredited official receipt (OR) for tax purposes.',
            $html
        );
        $this->assertStringContainsString('Print / Save as PDF', $html);
    }

    public function test_mixed_methods_have_no_suffix(): void
    {
        $appointment = $this->makeAppointment($this->client);
        $this->makePayment($appointment, 200, 'gcash');
        $this->makePayment($appointment, 100, 'maya');

        $html = $this->actingAs($this->client)
            ->get(route('account.bookings.receipt', $appointment->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Down payment<', $html);
        $this->assertStringNotContainsString('Down payment —', $html);
    }

    public function test_cash_only_receipt_has_no_down_payment_line(): void
    {
        $appointment = $this->makeAppointment($this->client, AppointmentStatus::Completed, ['booked_price' => 179]);
        $this->makePayment($appointment, 179, 'cash', 'branch');

        $html = $this->actingAs($this->client)
            ->get(route('account.bookings.receipt', $appointment->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Down payment', $html);
        $this->assertStringContainsString('Paid at branch — Cash', $html);
        $this->assertStringContainsString('₱179.00', $html);
    }

    public function test_client_gets_404_for_another_clients_appointment(): void
    {
        $appointment = $this->makeAppointment($this->otherClient);

        $this->actingAs($this->client)
            ->get(route('account.bookings.receipt', $appointment->id))
            ->assertNotFound();
    }

    public function test_client_redirected_for_own_non_completed_appointment(): void
    {
        $appointment = $this->makeAppointment($this->client, AppointmentStatus::Booked);

        $this->actingAs($this->client)
            ->get(route('account.bookings.receipt', $appointment->id))
            ->assertRedirect(route('account.bookings'))
            ->assertSessionHasErrors('receipt');
    }

    public function test_manager_can_view_any_completed_receipt(): void
    {
        $appointment = $this->makeAppointment($this->client);
        $this->makePayment($appointment, 300, 'gcash');

        $this->actingAs($this->manager)
            ->get(route('admin.appointments.receipt', $appointment->id))
            ->assertOk()
            ->assertSee($appointment->referenceCode())
            ->assertSee('₱300.00', false);
    }

    public function test_admin_receipt_refused_when_not_completed(): void
    {
        $appointment = $this->makeAppointment($this->client, AppointmentStatus::Booked);

        $this->actingAs($this->manager)
            ->get(route('admin.appointments.receipt', $appointment->id))
            ->assertRedirect()
            ->assertSessionHasErrors('receipt');
    }

    public function test_receipt_links_present_on_completed_cards(): void
    {
        $appointment = $this->makeAppointment($this->client);
        $this->makePayment($appointment, 300, 'gcash');

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee(route('account.bookings.receipt', $appointment->id), false);

        $this->actingAs($this->manager)->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->assertSee(route('admin.appointments.receipt', $appointment->id), false);

        $this->actingAs($this->manager)->get(route('admin.clients.show', $this->client))
            ->assertOk()
            ->assertSee(route('admin.appointments.receipt', $appointment->id), false);
    }
}
