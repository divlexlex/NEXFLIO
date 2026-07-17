<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request)
    {
        $status = $request->query('status', LeaveRequest::STATUS_PENDING);

        return view('admin.leaves.index', [
            'leaves' => LeaveRequest::with(['user:id,name', 'reviewer:id,name'])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->orderByDesc('created_at')
                ->paginate(15)
                ->withQueryString(),
            'currentStatus' => $status,
        ]);
    }

    public function review(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_DENIED])],
            'review_notes' => 'nullable|string|max:1000',
        ]);

        $leave = LeaveRequest::findOrFail($id);

        if ($leave->status !== LeaveRequest::STATUS_PENDING) {
            return back()->withErrors(['status' => 'This leave request has already been reviewed.']);
        }

        $leave->update([
            'status' => $validated['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        $this->notificationService->notify(
            $leave->user,
            'Leave request ' . $validated['status'],
            "Your {$leave->type} leave ({$leave->start_date->toDateString()} to {$leave->end_date->toDateString()}) was {$validated['status']}.",
        );

        return back()->with('success', 'Leave request ' . $validated['status'] . '.');
    }
}
