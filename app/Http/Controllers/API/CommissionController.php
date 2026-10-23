<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\User;
use App\Services\WeeklyPayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CommissionController extends Controller
{
    public function __construct(
        private readonly WeeklyPayrollService $payroll,
    ) {
    }

    public function mine(Request $request)
    {
        $week = $request->query('week');

        return response()->json(
            $this->summaryFor($request->user(), is_string($week) ? $week : null)
        );
    }

    public function forStaff(Request $request, $id)
    {
        $staff = User::where('role_id', User::ROLE_STAFF)->findOrFail($id);

        $week = $request->query('week');

        return response()->json(
            $this->summaryFor($staff, is_string($week) ? $week : null)
        );
    }

    /**
     * Real-time pay view for one week (Sunday–Saturday): daily base pay ×
     * days attended that week + the week's commission ledger (10% of each
     * completed service's price by default). ?week= accepts any date
     * inside the wanted week; invalid/missing = the current week.
     */
    private function summaryFor(User $staff, ?string $week): array
    {
        $weekDate = now()->toDateString();
        if (is_string($week) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) {
            try {
                $weekDate = Carbon::createFromFormat('Y-m-d', $week)->toDateString();
            } catch (\Throwable $e) {
                $weekDate = now()->toDateString();
            }
        }
        [$weekStart, $weekEnd] = $this->payroll->weekRange($weekDate);

        $row = $this->payroll->forStaff($staff, $weekStart);

        $entries = Commission::where('user_id', $staff->id)
            ->whereBetween('earned_at', [$weekStart.' 00:00:00', $weekEnd.' 23:59:59'])
            ->with('appointment.service:id,name')
            ->orderByDesc('earned_at')
            ->get();

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'staff' => ['id' => $staff->id, 'name' => $staff->name],
            'daily_base_pay' => number_format($row['daily_base_pay'], 2, '.', ''),
            'days_present' => $row['days_present'],
            'base_total' => number_format($row['base_total'], 2, '.', ''),
            'commission_rate' => (string) ($staff->staffProfile?->commission_rate ?? '10.00'),
            'commission_total' => number_format($row['commission'], 2, '.', ''),
            'grand_total' => number_format($row['total'], 2, '.', ''),
            'entries' => $entries,
        ];
    }
}
