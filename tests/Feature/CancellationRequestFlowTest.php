<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client cancellation-request flow end to end: reason categories,
 * drawer block + transition guard, single-render banners, refundable /
 * non-refundable approval, and zombie (already-terminal) review.
 */
class CancellationRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $staff;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->service = Service::create([
            'name' => 'Cancel Flow Facial', 'category' => 'Facial', 'price' => 800,
            'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function booking(AppointmentStatus $status = AppointmentStatus::Booked, float $paid = 800): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '10:00',
            'status' => $status,
            'booked_price' => 800,
        ]);
        Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => $paid,
            'method' => 'gcash',
            'status' => $status === AppointmentStatus::Unverified ? PaymentStatus::Pending : PaymentStatus::Verified,
            'verified_by' => $status === AppointmentStatus::Unverified ? null : $this->manager->id,
            'verified_at' => $status === AppointmentStatus::Unverified ? null : now(),
        ]);

        return $appointment;
    }

    private function pendingCancellation(Appointment $appointment, ?string $category = 'change_of_plans', ?string $reason = null): AppointmentChangeRequest
    {
        return AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $this->client->id,
            'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
            'status' => AppointmentChangeRequest::STATUS_PENDING,
            'reason' => $reason,
            'reason_category' => $category,
        ]);
    }

    public function test_cancellation_submit_without_category_fails(): void
    {
        $appointment = $this->booking();

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), [
                'type' => 'cancellation',
                'reason' => 'Some details but no category',
            ])
            ->assertSessionHasErrors('reason_category');

        $this->assertDatabaseCount('appointment_change_requests', 0);
    }

    public function test_cancellation_other_without_details_fails(): void
    {
        $appointment = $this->booking();

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), [
                'type' => 'cancellation',
                'reason_category' => 'other',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('appointment_change_requests', 0);
    }

    public function test_cancellation_valid_category_without_details_succeeds_and_admin_shows_label(): void
    {
        $appointment = $this->booking();

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), [
                'type' => 'cancellation',
                'reason_category' => 'schedule_conflict',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointment_change_requests', [
            'appointment_id' => $appointment->id,
            'reason_category' => 'schedule_conflict',
        ]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.appointment-requests'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Schedule conflict', $html);
    }

    public function test_cancellation_other_with_details_stores_both(): void
    {
        $appointment = $this->booking();

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), [
                'type' => 'cancellation',
                'reason_category' => 'other',
                'reason' => 'Family flew in unexpectedly',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointment_change_requests', [
            'appointment_id' => $appointment->id,
            'reason_category' => 'other',
            'reason' => 'Family flew in unexpectedly',
        ]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.appointment-requests'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Other (specify)', $html);
        $this->assertStringContainsString('Family flew in unexpectedly', $html);
    }

    public function test_reschedule_submit_unaffected_by_category_rules(): void
    {
        $appointment = $this->booking();
        $newDate = now()->addDays(6)->toDateString();

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), [
                'type' => 'reschedule',
                'requested_date' => $newDate,
                'requested_start_time' => '14:30',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointment_change_requests', [
            'appointment_id' => $appointment->id,
            'type' => 'reschedule',
            'reason_category' => null,
        ]);
    }

    public function test_drawer_payload_flags_pending_cancellation_with_request_id(): void
    {
        $appointment = $this->booking();
        $req = $this->pendingCancellation($appointment, 'health_reasons', 'Flu');

        $data = $this->actingAs($this->manager)
            ->getJson(route('admin.appointments.feed'))
            ->assertOk()
            ->json();

        $event = collect($data)->firstWhere('id', $appointment->id);
        $this->assertNotNull($event);
        $this->assertTrue($event['extendedProps']['pending_cancellation']);
        $this->assertSame($req->id, $event['extendedProps']['pending_cancellation_request_id']);
        $this->assertSame('Flu', $event['extendedProps']['cancellation_reason']);
        $this->assertSame([], $event['extendedProps']['actions']);
    }

    public function test_transition_to_in_service_refused_while_cancellation_pending_but_approval_works(): void
    {
        $appointment = $this->booking();
        $req = $this->pendingCancellation($appointment);

        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $appointment->id),
            ['status' => AppointmentStatus::InService->value]
        )->assertSessionHasErrors('status');

        $this->assertSame(AppointmentStatus::Booked, $appointment->fresh()->status);

        // The cancellation approval itself still works (targets Cancelled,
        // so the In-Service guard never applies).
        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), [
                'decision' => 'approve',
                'refund_decision' => 'non_refundable',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertSame('approved', $req->fresh()->status);
    }

    public function test_change_requests_page_renders_flashed_error_exactly_once(): void
    {
        $appointment = $this->booking();
        $req = $this->pendingCancellation($appointment);

        // Review it once so the second review errors.
        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), [
                'decision' => 'reject',
                'review_note' => 'Too late.',
            ])
            ->assertRedirect();

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), ['decision' => 'reject'])
            ->assertSessionHasErrors('decision');

        $html = $this->actingAs($this->manager)
            ->from(route('admin.appointment-requests'))
            ->followingRedirects()
            ->patch(route('admin.appointment-requests.review', $req), ['decision' => 'reject'])
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'This request has already been reviewed.'));
        $this->assertStringContainsString('data-nx-flash', $html);
    }

    public function test_approve_refundable_marks_refund_and_client_lines_update_after_sent(): void
    {
        $appointment = $this->booking();
        $req = $this->pendingCancellation($appointment);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), [
                'decision' => 'approve',
                'refund_decision' => 'refundable',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $req->fresh()->status);
        $this->assertSame('refundable', $req->fresh()->refund_decision);
        $this->assertSame('requested', $appointment->payment->fresh()->refund_status);

        // Exactly one approval notification, mentioning the ₱800 refund.
        $notes = Notification::where('user_id', $this->client->id)->get();
        $this->assertCount(1, $notes);
        $this->assertStringContainsString('₱800.00', $notes->first()->body);
        $this->assertStringContainsString('sent back', $notes->first()->body);

        // My Bookings: refund expected line with the true amount.
        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Refund expected: ₱800.00')
            ->assertSee('the branch will send it back to your payment method.');

        // Approved tab badge + Payments card refund line (shared helper).
        $this->actingAs($this->manager)
            ->get(route('admin.appointment-requests', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('Refundable ₱800.00');

        $this->actingAs($this->manager)->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->assertSee('Refund requested: ₱800.00')
            ->assertSee('Mark refund sent');

        // After Mark refund sent, the client line reads Refund sent.
        $this->actingAs($this->manager)
            ->patch(route('admin.payments.refund-sent', $appointment->payment->id))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('sent', $appointment->payment->fresh()->refund_status);

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Refund sent: ₱800.00');
    }

    public function test_approve_non_refundable_shows_policy_line_and_badge(): void
    {
        $appointment = $this->booking();
        $req = $this->pendingCancellation($appointment);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), [
                'decision' => 'approve',
                'refund_decision' => 'non_refundable',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $req->fresh()->status);
        $this->assertSame('non_refundable', $req->fresh()->refund_decision);
        $this->assertNull($appointment->payment->fresh()->refund_status);

        $notes = Notification::where('user_id', $this->client->id)->get();
        $this->assertCount(1, $notes);
        $this->assertStringContainsString('non-refundable', $notes->first()->body);

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Down payment is non-refundable (per booking policy).');

        $this->actingAs($this->manager)
            ->get(route('admin.appointment-requests', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('Non-refundable');
    }

    public function test_non_refundable_with_nothing_paid_shows_no_refund_line(): void
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDays(3)->toDateString(),
            'start_time' => '11:00',
            'status' => AppointmentStatus::Unverified,
            'booked_price' => 800,
        ]);
        Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => 300,
            'method' => 'gcash',
            'status' => PaymentStatus::Pending,
        ]);
        $req = $this->pendingCancellation($appointment);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), [
                'decision' => 'approve',
                'refund_decision' => 'non_refundable',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertDontSee('Down payment is non-refundable');
    }

    public function test_zombie_already_cancelled_closes_as_approved_with_success(): void
    {
        $appointment = $this->booking(AppointmentStatus::Cancelled);
        // Verified money stays held on the cancelled booking.
        $this->assertEquals(800, $appointment->verifiedPaid());
        $req = $this->pendingCancellation($appointment);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), [
                'decision' => 'approve',
                'refund_decision' => 'refundable',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'That appointment was already cancelled — the request has been closed as approved.');

        $this->assertSame('approved', $req->fresh()->status);
        $this->assertSame('refundable', $req->fresh()->refund_decision);
        $this->assertSame('requested', $appointment->payment->fresh()->refund_status);

        // No longer pending.
        $this->assertSame(0, AppointmentChangeRequest::where('status', 'pending')->count());
        $this->actingAs($this->manager)->get(route('admin.appointment-requests'))
            ->assertOk()
            ->assertSee('No requests match your filters.');
    }

    public function test_zombie_completed_closes_as_rejected_with_success(): void
    {
        $appointment = $this->booking(AppointmentStatus::Completed);
        $req = $this->pendingCancellation($appointment);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), [
                'decision' => 'approve',
                'refund_decision' => 'refundable',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $req->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame('Appointment already Completed', $fresh->review_note);
        $this->assertSame(0, AppointmentChangeRequest::where('status', 'pending')->count());
    }

    private function decidedCancellation(Appointment $appointment, string $decision): AppointmentChangeRequest
    {
        return AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $this->client->id,
            'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
            'status' => AppointmentChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $this->manager->id,
            'refund_decision' => $decision,
        ]);
    }

    public function test_remark_prefers_stored_refund_decision_over_refund_status(): void
    {
        // Refundable decision but no refund row yet (e.g. nothing marked) —
        // legacy logic would say no_refund; the decision wins.
        $refundable = $this->booking(AppointmentStatus::Cancelled);
        $this->decidedCancellation($refundable, 'refundable');
        $this->assertSame('refund', $refundable->fresh()->cancellationRefundRemark());

        // Non-refundable decision — wins even with money paid and no row.
        $kept = $this->booking(AppointmentStatus::Cancelled);
        $this->decidedCancellation($kept, 'non_refundable');
        $this->assertSame('no_refund', $kept->fresh()->cancellationRefundRemark());

        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>Refund</span>', $html);
        $this->assertStringContainsString('>No refund</span>', $html);
    }

    public function test_refund_filters_prefer_stored_decision_with_legacy_fallback(): void
    {
        // Stored decisions, neither with a refund_status row.
        $decidedRefund = $this->booking(AppointmentStatus::Cancelled);
        $decidedRefund->update(['start_time' => '09:00']);
        $this->decidedCancellation($decidedRefund, 'refundable');

        $decidedKept = $this->booking(AppointmentStatus::Cancelled);
        $decidedKept->update(['start_time' => '09:30']);
        $this->decidedCancellation($decidedKept, 'non_refundable');

        // Legacy decision-less approvals keep the refund_status behaviour.
        $legacyRefund = $this->booking(AppointmentStatus::Cancelled);
        $legacyRefund->update(['start_time' => '10:00']);
        AppointmentChangeRequest::create([
            'appointment_id' => $legacyRefund->id,
            'requested_by' => $this->client->id,
            'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
            'status' => AppointmentChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $this->manager->id,
        ]);
        $legacyRefund->payment->update(['refund_status' => 'requested', 'refund_requested_at' => now()]);

        $legacyKept = $this->booking(AppointmentStatus::Cancelled);
        $legacyKept->update(['start_time' => '10:30']);
        AppointmentChangeRequest::create([
            'appointment_id' => $legacyKept->id,
            'requested_by' => $this->client->id,
            'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
            'status' => AppointmentChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $this->manager->id,
        ]);

        $refundHtml = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'refund']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($decidedRefund->referenceCode(), $refundHtml);
        $this->assertStringContainsString($legacyRefund->referenceCode(), $refundHtml);
        $this->assertStringNotContainsString($decidedKept->referenceCode(), $refundHtml);
        $this->assertStringNotContainsString($legacyKept->referenceCode(), $refundHtml);

        $keptHtml = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'no_refund']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($decidedKept->referenceCode(), $keptHtml);
        $this->assertStringContainsString($legacyKept->referenceCode(), $keptHtml);
        $this->assertStringNotContainsString($decidedRefund->referenceCode(), $keptHtml);
        $this->assertStringNotContainsString($legacyRefund->referenceCode(), $keptHtml);
    }
}
