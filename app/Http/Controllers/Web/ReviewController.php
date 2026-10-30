<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Review;
use Illuminate\Http\Request;

/**
 * Client feedback on Completed visits from My Bookings — a comment
 * only, no star ratings (sha, 2026-10-30). One feedback entry per
 * appointment: resubmitting edits the existing row via updateOrCreate.
 * Legacy rating columns are left untouched (NULL for new rows).
 */
class ReviewController extends Controller
{
    public function store(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->user_id === $request->user()->id, 403);
        abort_unless($appointment->status === AppointmentStatus::Completed, 403);

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        Review::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'user_id' => $request->user()->id,
                'comment' => $validated['comment'],
            ]
        );

        return back()->with('success', 'Thank you! Your feedback has been saved.');
    }
}
