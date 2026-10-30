<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin Feedback page (Owner / Manager) — read-only monitoring of
 * client feedback from completed appointments: total count, an
 * optional personnel filter, and the newest-first feedback list. No
 * moderation here. Star ratings are no longer collected or shown
 * (sha, 2026-10-30); legacy rating columns are ignored.
 */
class AdminReviewsController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('personnel_id');
        $hasFilter = $filter !== null && $filter !== '';
        $personnelId = (int) $filter;

        $reviews = Review::with(['user', 'appointment.service', 'appointment.personnel', 'appointment.user'])
            ->when(
                $hasFilter,
                fn ($query) => $query->whereHas(
                    'appointment',
                    fn ($query) => $query->where('personnel_id', $personnelId)
                )
            )
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $personnelOptions = User::where('role_id', User::ROLE_STAFF)
            ->whereHas('assignedAppointments.review')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.reviews.index', [
            'totalFeedback' => Review::count(),
            'reviews' => $reviews,
            'personnelOptions' => $personnelOptions,
            'personnelId' => $personnelId,
        ]);
    }
}
