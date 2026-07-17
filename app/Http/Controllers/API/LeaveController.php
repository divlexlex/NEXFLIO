<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequest;
use App\Models\LeaveRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function mine(Request $request)
    {
        return LeaveRequest::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function store(StoreLeaveRequest $request)
    {
        $leave = LeaveRequest::create([
            'user_id' => $request->user()->id,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'type' => $request->input('type'),
            'reason' => $request->input('reason'),
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Leave request submitted.',
            'leave' => $leave,
        ], 201);
    }

    public function index(Request $request)
    {
        return LeaveRequest::with(['user:id,name', 'reviewer:id,name'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    public function review(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_DENIED])],
            'review_notes' => 'nullable|string|max:1000',
        ]);

        $leave = LeaveRequest::findOrFail($id);

        if ($leave->status !== LeaveRequest::STATUS_PENDING) {
            return response()->json(['message' => 'This leave request has already been reviewed.'], 422);
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

        return response()->json([
            'message' => 'Leave request ' . $validated['status'] . '.',
            'leave' => $leave->fresh(['user:id,name', 'reviewer:id,name']),
        ]);
    }
}
