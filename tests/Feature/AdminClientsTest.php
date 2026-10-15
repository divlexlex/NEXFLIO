<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\ClientProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin Clients module — searchable client list, per-client profile with
 * stats and full appointment archive, and staff-written preference notes.
 */
class AdminClientsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;

    private User $manager;

    private User $staff;

    private User $clientUser;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->clientUser = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->service = Service::where('status', 'active')->firstOrFail();
    }

    private function makeClient(string $name, string $email, bool $withProfile = true): User
    {
        $client = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);

        if ($withProfile) {
            ClientProfile::create([
                'user_id' => $client->id,
                'first_name' => explode(' ', $name)[0],
                'last_name' => explode(' ', $name)[1] ?? '',
                'mobile_number' => '09170000001',
            ]);
        }

        return $client;
    }

    private function makeAppointment(User $client, AppointmentStatus $status = AppointmentStatus::Booked): Appointment
    {
        return Appointment::create([
            'user_id' => $client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDays(3)->toDateString(),
            'start_time' => '14:00:00',
            'status' => $status,
        ]);
    }

    private function makeVerifiedPayment(Appointment $appointment, float $amount): Payment
    {
        return Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => $amount,
            'method' => 'cash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);
    }

    public function test_list_access_owner_manager_ok_staff_client_forbidden(): void
    {
        $this->actingAs($this->owner)->get(route('admin.clients'))->assertOk();
        $this->actingAs($this->manager)->get(route('admin.clients'))->assertOk();

        $this->actingAs($this->staff)->get(route('admin.clients'))->assertForbidden();
        $this->actingAs($this->clientUser)->get(route('admin.clients'))->assertForbidden();
    }

    public function test_list_search_finds_by_email_and_hides_on_nonsense(): void
    {
        $found = $this->makeClient('Searchable Person', 'searchable.person@nexflio.test');
        $hidden = $this->makeClient('Other Person', 'other.person@nexflio.test');

        $html = $this->actingAs($this->manager)
            ->get(route('admin.clients', ['q' => 'searchable.person@nexflio.test']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($found->name, $html);
        $this->assertStringNotContainsString($hidden->name, $html);

        $empty = $this->actingAs($this->manager)
            ->get(route('admin.clients', ['q' => 'zzz-no-such-client-xyz']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($found->name, $empty);
    }

    public function test_show_history_stats_and_total_paid(): void
    {
        $client = $this->makeClient('History Person', 'history.person@nexflio.test');

        $completed = $this->makeAppointment($client, AppointmentStatus::Completed);
        $cancelled = $this->makeAppointment($client, AppointmentStatus::Cancelled);

        $this->makeVerifiedPayment($completed, 500);
        $this->makeVerifiedPayment($cancelled, 100);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.clients.show', $client))
            ->assertOk()
            ->getContent();

        // Both statuses appear in the archive.
        $this->assertStringContainsString($completed->referenceCode(), $html);
        $this->assertStringContainsString($cancelled->referenceCode(), $html);

        // Stats: 2 bookings, 1 completed, 1 cancelled/no-show, ₱600 paid.
        $this->assertStringContainsString('₱'.number_format(600, 2), $html);
    }

    public function test_show_for_non_client_returns_404(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.clients.show', $this->staff))
            ->assertNotFound();
    }

    public function test_manager_can_save_notes_and_they_render(): void
    {
        $client = $this->makeClient('Notes Person', 'notes.person@nexflio.test');

        $this->actingAs($this->manager)
            ->patch(route('admin.clients.notes', $client), [
                'preferences_notes' => 'Prefers morning slots, allergic to latex.',
            ])
            ->assertRedirect(route('admin.clients.show', $client))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('client_profiles', [
            'user_id' => $client->id,
            'preferences_notes' => 'Prefers morning slots, allergic to latex.',
        ]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.clients.show', $client))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Prefers morning slots, allergic to latex.', $html);
    }

    public function test_notes_create_profile_row_when_missing(): void
    {
        $client = $this->makeClient('NoProfile Person', 'noprofile.person@nexflio.test', withProfile: false);
        $this->assertNull(ClientProfile::where('user_id', $client->id)->first());

        $this->actingAs($this->manager)
            ->patch(route('admin.clients.notes', $client), [
                'preferences_notes' => 'First note for a profile-less client.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $profile = ClientProfile::where('user_id', $client->id)->firstOrFail();
        $this->assertSame('First note for a profile-less client.', $profile->preferences_notes);
        $this->assertSame('NoProfile', $profile->first_name);
        $this->assertSame('Person', $profile->last_name);
    }

    public function test_staff_cannot_save_notes(): void
    {
        $client = $this->makeClient('Notes Person', 'notes.person@nexflio.test');

        $this->actingAs($this->staff)
            ->patch(route('admin.clients.notes', $client), [
                'preferences_notes' => 'Should never save.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('client_profiles', [
            'user_id' => $client->id,
            'preferences_notes' => 'Should never save.',
        ]);
    }
}
