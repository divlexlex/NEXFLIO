<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client ratings of Completed visits — one review per appointment, editable
 * by resubmitting, personnel rating required only when staff was assigned.
 */
class ReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $otherClient;

    private User $staff;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->service = Service::where('status', 'active')->firstOrFail();

        $this->otherClient = User::create([
            'name' => 'Other Client',
            'email' => 'other.client@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);
    }

    private function makeAppointment(User $client, AppointmentStatus $status = AppointmentStatus::Completed, ?User $personnel = null): Appointment
    {
        return Appointment::create([
            'user_id' => $client->id,
            'service_id' => $this->service->id,
            'personnel_id' => ($personnel ?? $this->staff)->id,
            'appointment_date' => now()->subDay()->toDateString(),
            'start_time' => '14:00:00',
            'status' => $status,
        ]);
    }

    private function reviewPayload(array $overrides = []): array
    {
        return array_merge([
            'service_rating' => 5,
            'personnel_rating' => 4,
            'comment' => 'Great visit!',
        ], $overrides);
    }

    public function test_client_can_review_their_completed_appointment(): void
    {
        $appointment = $this->makeAppointment($this->client);

        $this->actingAs($this->client)
            ->post(route('account.bookings.review.store', $appointment->id), $this->reviewPayload())
            ->assertRedirect()
            ->assertSessionHas('success', 'Thank you! Your review has been saved.');

        $this->assertDatabaseHas('reviews', [
            'appointment_id' => $appointment->id,
            'user_id' => $this->client->id,
            'service_rating' => 5,
            'personnel_rating' => 4,
            'comment' => 'Great visit!',
        ]);
    }

    public function test_review_on_non_completed_appointment_is_forbidden(): void
    {
        $appointment = $this->makeAppointment($this->client, AppointmentStatus::Booked);

        $this->actingAs($this->client)
            ->post(route('account.bookings.review.store', $appointment->id), $this->reviewPayload())
            ->assertForbidden();

        $this->assertSame(0, Review::where('appointment_id', $appointment->id)->count());
    }

    public function test_review_on_another_clients_appointment_is_forbidden(): void
    {
        $appointment = $this->makeAppointment($this->otherClient);

        $this->actingAs($this->client)
            ->post(route('account.bookings.review.store', $appointment->id), $this->reviewPayload())
            ->assertForbidden();

        $this->assertSame(0, Review::where('appointment_id', $appointment->id)->count());
    }

    public function test_out_of_range_service_rating_fails_validation(): void
    {
        foreach ([0, 6] as $rating) {
            $appointment = $this->makeAppointment($this->client);

            $this->actingAs($this->client)
                ->post(
                    route('account.bookings.review.store', $appointment->id),
                    $this->reviewPayload(['service_rating' => $rating])
                )
                ->assertSessionHasErrors('service_rating');

            $this->assertSame(0, Review::where('appointment_id', $appointment->id)->count());
        }
    }

    public function test_submitting_twice_updates_the_same_row(): void
    {
        $appointment = $this->makeAppointment($this->client);

        $this->actingAs($this->client)
            ->post(route('account.bookings.review.store', $appointment->id), $this->reviewPayload())
            ->assertRedirect();

        $this->actingAs($this->client)
            ->post(
                route('account.bookings.review.store', $appointment->id),
                $this->reviewPayload(['service_rating' => 3, 'comment' => 'Changed my mind.'])
            )
            ->assertRedirect();

        $this->assertSame(1, Review::where('appointment_id', $appointment->id)->count());
        $this->assertDatabaseHas('reviews', [
            'appointment_id' => $appointment->id,
            'service_rating' => 3,
            'comment' => 'Changed my mind.',
        ]);
    }

    public function test_personnel_rating_optional_without_personnel_required_with(): void
    {
        $noPersonnel = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => null,
            'appointment_date' => now()->subDay()->toDateString(),
            'start_time' => '14:00:00',
            'status' => AppointmentStatus::Completed,
        ]);

        $this->actingAs($this->client)
            ->post(
                route('account.bookings.review.store', $noPersonnel->id),
                ['service_rating' => 5, 'comment' => 'No staff assigned.']
            )
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reviews', [
            'appointment_id' => $noPersonnel->id,
            'service_rating' => 5,
            'personnel_rating' => null,
        ]);

        $withPersonnel = $this->makeAppointment($this->client);

        $this->actingAs($this->client)
            ->post(
                route('account.bookings.review.store', $withPersonnel->id),
                ['service_rating' => 5, 'comment' => 'Missing personnel rating.']
            )
            ->assertSessionHasErrors('personnel_rating');

        $this->assertSame(0, Review::where('appointment_id', $withPersonnel->id)->count());
    }

    public function test_bookings_page_shows_rate_or_review_only_on_completed(): void
    {
        $completed = $this->makeAppointment($this->client);
        $booked = $this->makeAppointment($this->client, AppointmentStatus::Booked);

        $html = $this->actingAs($this->client)->get(route('account.bookings'))->assertOk()->getContent();

        $this->assertStringContainsString('Rate your visit', $html);
        $this->assertStringNotContainsString('Your review', $html);

        // The Booked card carries neither string: count occurrences — exactly
        // one "Rate your visit" (the completed card) and no "Your review".
        $this->assertSame(1, substr_count($html, 'Rate your visit'));

        $this->actingAs($this->client)
            ->post(route('account.bookings.review.store', $completed->id), $this->reviewPayload())
            ->assertRedirect();

        $after = $this->actingAs($this->client)->get(route('account.bookings'))->assertOk()->getContent();

        $this->assertStringContainsString('Your review', $after);
        $this->assertStringNotContainsString('Rate your visit', $after);
        // The Booked card gets no modal at all.
        $this->assertStringNotContainsString('id="reviewModal-'.$booked->id.'"', $after);
        $this->assertStringContainsString('id="reviewModal-'.$completed->id.'"', $after);
    }
}
