<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Review;
use Illuminate\Http\Request;

/**
 * Client ratings of Completed visits from My Bookings — 1–5 stars for the
 * service and the personnel plus an optional comment. One review per
 * appointment: resubmitting edits the existing row via updateOrCreate.
 */
class ReviewController extends Controller
{
    public function store(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->user_id === $request->user()->id, 403);
        abort_unless($appointment->status === AppointmentStatus::Completed, 403);

        $validated = $request->validate([
            'service_rating' => 'required|integer|min:1|max:5',
            'personnel_rating' => $appointment->personnel_id
                ? 'required|integer|min:1|max:5'
                : 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        Review::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'user_id' => $request->user()->id,
                'service_rating' => $validated['service_rating'],
                'personnel_rating' => $validated['personnel_rating'] ?? null,
                'comment' => $validated['comment'] ?? null,
            ]
        );

        return back()->with('success', 'Thank you! Your review has been saved.');
    }
}
