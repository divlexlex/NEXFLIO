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
 * Admin Reviews page — read-only monitoring of client ratings: overall
 * averages, personnel filter, and the newest-first review list.
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

    private function makeReviewedAppointment(
        User $client,
        User $personnel,
        int $serviceRating,
        ?int $personnelRating,
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
            'service_rating' => $serviceRating,
            'personnel_rating' => $personnelRating,
            'comment' => 'Comment '.$appointment->id,
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

    public function test_stats_list_and_newest_first_order(): void
    {
        $oldest = $this->makeReviewedAppointment($this->clientUser, $this->staff, 5, 5, 3);
        $middle = $this->makeReviewedAppointment($this->clientUser, $this->staff, 4, null, 2);
        $newest = $this->makeReviewedAppointment($this->clientUser, $this->staff, 3, 3, 1);

        $html = $this->actingAs($this->manager)->get(route('admin.reviews'))->assertOk()->getContent();

        // Total 3; service avg (5+4+3)/3 = 4.0; personnel avg (5+3)/2 = 4.0.
        $this->assertStringContainsString('<div class="display-6">3</div>', $html);
        $this->assertSame(2, substr_count($html, '4.0 / 5'));

        $this->assertStringContainsString($this->clientUser->name, $html);
        $this->assertStringContainsString($this->service->name, $html);
        $this->assertStringContainsString($newest->appointment->referenceCode(), $html);
        $this->assertStringContainsString($oldest->appointment->referenceCode(), $html);

        $newestPos = strpos($html, $newest->appointment->referenceCode());
        $middlePos = strpos($html, $middle->appointment->referenceCode());
        $oldestPos = strpos($html, $oldest->appointment->referenceCode());
        $this->assertTrue($newestPos < $middlePos && $middlePos < $oldestPos);
    }

    public function test_personnel_filter_scopes_list_but_not_stats(): void
    {
        $otherStaff = User::create([
            'name' => 'Review Other Staff',
            'email' => 'review.other.staff@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);

        $mine = $this->makeReviewedAppointment($this->clientUser, $this->staff, 5, 5, 2);
        $theirs = $this->makeReviewedAppointment($this->clientUser, $otherStaff, 3, 3, 1);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.reviews', ['personnel_id' => $this->staff->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($mine->appointment->referenceCode(), $html);
        $this->assertStringNotContainsString($theirs->appointment->referenceCode(), $html);

        // Stats still reflect ALL reviews.
        $this->assertStringContainsString('4.0 / 5', $html);
    }

    public function test_empty_state_shows_dashes(): void
    {
        $this->assertSame(0, Review::count());

        $html = $this->actingAs($this->manager)->get(route('admin.reviews'))->assertOk()->getContent();

        $this->assertStringContainsString('No reviews yet', $html);
        $this->assertStringContainsString('—', $html);
    }
}
