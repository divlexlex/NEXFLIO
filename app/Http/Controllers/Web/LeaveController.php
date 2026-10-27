<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\LeaveConflicts;
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

        $leaves = LeaveRequest::with(['user:id,name,role_id', 'reviewer:id,name'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.leaves.index', [
            'leaves' => $leaves,
            'currentStatus' => $status,
            // Display-only: the requester's own active appointments inside
            // the requested dates, so the approver decides informed.
            'leaveConflicts' => LeaveConflicts::forLeaves($leaves->getCollection()),
        ]);
    }

    public function review(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_DENIED])],
            'review_notes' => 'nullable|string|max:1000',
        ]);

        $leave = LeaveRequest::with('user')->findOrFail($id);

        // Managers' leave is the owner's call: a manager can never approve
        // (or deny) their own request, and no other manager can do it for
        // them. Enforced here, server-side — hiding the button is not enough.
        if ($leave->user
            && $leave->user->role_id === User::ROLE_MANAGER
            && $request->user()->role_id !== User::ROLE_SUPER_ADMIN) {
            return back()->withErrors([
                'status' => "A manager's leave request can only be reviewed by the owner.",
            ]);
        }

        // Nobody reviews their own leave.
        if ($leave->user_id === $request->user()->id) {
            return back()->withErrors([
                'status' => 'You cannot review your own leave request.',
            ]);
        }

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
