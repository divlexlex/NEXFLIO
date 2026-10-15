<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin Reviews page (Owner / Manager) — read-only monitoring of client
 * ratings from completed appointments: overall averages, an optional
 * personnel filter, and the newest-first review list. No moderation here.
 */
class AdminReviewsController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('personnel_id');
        $hasFilter = $filter !== null && $filter !== '';
        $personnelId = (int) $filter;

        $serviceAvg = Review::avg('service_rating');
        $personnelAvg = Review::whereNotNull('personnel_rating')->avg('personnel_rating');

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
            'totalReviews' => Review::count(),
            'serviceAvg' => $serviceAvg !== null ? round((float) $serviceAvg, 1) : null,
            'personnelAvg' => $personnelAvg !== null ? round((float) $personnelAvg, 1) : null,
            'reviews' => $reviews,
            'personnelOptions' => $personnelOptions,
            'personnelId' => $personnelId,
        ]);
    }
}
