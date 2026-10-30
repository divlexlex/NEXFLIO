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
 * Client feedback on Completed visits — a comment only, no star
 * ratings (sha, 2026-10-30). One feedback entry per appointment,
 * editable by resubmitting. New rows carry NULL ratings (see
 * 2026_10_30_000001_make_review_ratings_nullable).
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

    private function feedbackPayload(array $overrides = []): array
    {
        return array_merge([
            'comment' => 'Great visit!',
        ], $overrides);
    }

    public function test_client_can_leave_feedback_on_completed_appointment(): void
    {
        $appointment = $this->makeAppointment($this->client);

        $this->actingAs($this->client)
            ->post(route('account.bookings.review.store', $appointment->id), $this->feedbackPayload())
            ->assertRedirect()
            ->assertSessionHas('success', 'Thank you! Your feedback has been saved.');

        $this->assertDatabaseHas('reviews', [
            'appointment_id' => $appointment->id,
            'user_id' => $this->client->id,
            'service_rating' => null,
            'personnel_rating' => null,
            'comment' => 'Great visit!',
        ]);
    }

    public function test_feedback_on_non_completed_appointment_is_forbidden(): void
    {
        $appointment = $this->makeAppointment($this->client, AppointmentStatus::Booked);

        $this->actingAs($this->client)
            ->post(route('account.bookings.review.store', $appointment->id), $this->feedbackPayload())
            ->assertForbidden();

        $this->assertSame(0, Review::where('appointment_id', $appointment->id)->count());
    }

    public function test_feedback_on_another_clients_appointment_is_forbidden(): void
    {
        $appointment = $this->makeAppointment($this->otherClient);

        $this->actingAs($this->client)
            ->post(route('account.bookings.review.store', $appointment->id), $this->feedbackPayload())
            ->assertForbidden();

        $this->assertSame(0, Review::where('appointment_id', $appointment->id)->count());
    }

    public function test_feedback_validation_rejects_empty_and_too_long(): void
    {
        foreach (['', str_repeat('a', 1001)] as $comment) {
            $appointment = $this->makeAppointment($this->client);

            $this->actingAs($this->client)
                ->post(
                    route('account.bookings.review.store', $appointment->id),
                    $this->feedbackPayload(['comment' => $comment])
                )
                ->assertSessionHasErrors('comment');

            $this->assertSame(0, Review::where('appointment_id', $appointment->id)->count());
        }
    }

    public function test_submitting_twice_updates_the_same_row(): void
    {
        $appointment = $this->makeAppointment($this->client);

        $this->actingAs($this->client)
            ->post(route('account.bookings.review.store', $appointment->id), $this->feedbackPayload())
            ->assertRedirect();

        $this->actingAs($this->client)
            ->post(
                route('account.bookings.review.store', $appointment->id),
                $this->feedbackPayload(['comment' => 'Changed my mind.'])
            )
            ->assertRedirect();

        $this->assertSame(1, Review::where('appointment_id', $appointment->id)->count());
        $this->assertDatabaseHas('reviews', [
            'appointment_id' => $appointment->id,
            'comment' => 'Changed my mind.',
        ]);
    }

    public function test_bookings_page_shows_feedback_button_only_on_completed(): void
    {
        $completed = $this->makeAppointment($this->client);
        $booked = $this->makeAppointment($this->client, AppointmentStatus::Booked);

        $html = $this->actingAs($this->client)->get(route('account.bookings'))->assertOk()->getContent();

        $this->assertStringContainsString('Leave feedback', $html);
        $this->assertStringNotContainsString('Your feedback', $html);
        $this->assertStringNotContainsString('Rate your visit', $html);

        // The Booked card carries neither string: count occurrences —
        // exactly two "Leave feedback" on the completed card (its
        // button plus its modal title, which now matches the button;
        // the old modal title "How was your visit?" was why this
        // count used to be one).
        $this->assertSame(2, substr_count($html, 'Leave feedback'));

        $this->actingAs($this->client)
            ->post(route('account.bookings.review.store', $completed->id), $this->feedbackPayload())
            ->assertRedirect();

        $after = $this->actingAs($this->client)->get(route('account.bookings'))->assertOk()->getContent();

        $this->assertStringContainsString('Your feedback', $after);
        $this->assertStringContainsString('Great visit!', $after);
        $this->assertStringContainsString('Edit feedback', $after);
        $this->assertStringNotContainsString('Leave feedback', $after);
        $this->assertStringNotContainsString('Rate your visit', $after);
        // The Booked card gets no modal at all.
        $this->assertStringNotContainsString('id="reviewModal-'.$booked->id.'"', $after);
        $this->assertStringContainsString('id="reviewModal-'.$completed->id.'"', $after);
    }
}
