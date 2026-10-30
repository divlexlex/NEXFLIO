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
 * Admin Feedback page — read-only monitoring of client feedback:
 * total count, personnel filter, and the newest-first feedback list.
 * No star ratings and no averages (sha, 2026-10-30): feedback only.
 */
class AdminReviewsTest extends TestCase
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

    private function makeFeedbackAppointment(
        User $client,
        User $personnel,
        string $comment,
        int $daysAgo = 1
    ): Review {
        $appointment = Appointment::create([
            'user_id' => $client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $personnel->id,
            'appointment_date' => now()->subDays($daysAgo + 1)->toDateString(),
            'start_time' => '14:00:00',
            'status' => AppointmentStatus::Completed,
        ]);

        $review = Review::create([
            'appointment_id' => $appointment->id,
            'user_id' => $client->id,
            'service_rating' => null,
            'personnel_rating' => null,
            'comment' => $comment,
        ]);
        $review->created_at = now()->subDays($daysAgo);
        $review->save();

        return $review->fresh();
    }

    public function test_list_access_owner_manager_ok_staff_client_forbidden(): void
    {
        $this->actingAs($this->owner)->get(route('admin.reviews'))->assertOk();
        $this->actingAs($this->manager)->get(route('admin.reviews'))->assertOk();

        $this->actingAs($this->staff)->get(route('admin.reviews'))->assertForbidden();
        $this->actingAs($this->clientUser)->get(route('admin.reviews'))->assertForbidden();
    }

    public function test_total_list_and_newest_first_order(): void
    {
        $oldest = $this->makeFeedbackAppointment($this->clientUser, $this->staff, 'Oldest feedback', 3);
        $middle = $this->makeFeedbackAppointment($this->clientUser, $this->staff, 'Middle feedback', 2);
        $newest = $this->makeFeedbackAppointment($this->clientUser, $this->staff, 'Newest feedback', 1);

        $html = $this->actingAs($this->manager)->get(route('admin.reviews'))->assertOk()->getContent();

        $this->assertStringContainsString('<div class="display-6">3</div>', $html);
        $this->assertStringContainsString('Total feedback', $html);
        $this->assertStringContainsString('Oldest feedback', $html);
        $this->assertStringContainsString('Middle feedback', $html);
        $this->assertStringContainsString('Newest feedback', $html);

        // No averages, no stars anywhere on the page.
        $this->assertStringNotContainsString('Average', $html);
        $this->assertStringNotContainsString('★', $html);

        $newestPos = strpos($html, $newest->appointment->referenceCode());
        $middlePos = strpos($html, $middle->appointment->referenceCode());
        $oldestPos = strpos($html, $oldest->appointment->referenceCode());
        $this->assertTrue($newestPos < $middlePos && $middlePos < $oldestPos);
    }

    public function test_personnel_filter_scopes_list_but_not_total(): void
    {
        $otherStaff = User::create([
            'name' => 'Review Other Staff',
            'email' => 'review.other.staff@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);

        $mine = $this->makeFeedbackAppointment($this->clientUser, $this->staff, 'Mine feedback', 2);
        $theirs = $this->makeFeedbackAppointment($this->clientUser, $otherStaff, 'Theirs feedback', 1);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.reviews', ['personnel_id' => $this->staff->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($mine->appointment->referenceCode(), $html);
        $this->assertStringNotContainsString($theirs->appointment->referenceCode(), $html);

        // Total still reflects ALL feedback.
        $this->assertStringContainsString('<div class="display-6">2</div>', $html);
    }

    public function test_empty_state(): void
    {
        $this->assertSame(0, Review::count());

        $html = $this->actingAs($this->manager)->get(route('admin.reviews'))->assertOk()->getContent();

        $this->assertStringContainsString('No feedback yet', $html);
        $this->assertStringContainsString('<div class="display-6">0</div>', $html);
    }

    public function test_legacy_star_only_row_shows_dash_not_stars(): void
    {
        $appointment = Appointment::create([
            'user_id' => $this->clientUser->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->subDay()->toDateString(),
            'start_time' => '14:00:00',
            'status' => AppointmentStatus::Completed,
        ]);

        Review::create([
            'appointment_id' => $appointment->id,
            'user_id' => $this->clientUser->id,
            'service_rating' => 5,
            'personnel_rating' => 5,
            'comment' => null,
        ]);

        $html = $this->actingAs($this->manager)->get(route('admin.reviews'))->assertOk()->getContent();

        $this->assertStringContainsString($appointment->referenceCode(), $html);
        $this->assertStringNotContainsString('★', $html);
    }
}
