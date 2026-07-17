<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function mine(Request $request)
    {
        return response()->json(
            $this->summaryFor($request->user(), $request->query('month', now()->format('Y-m')))
        );
    }

    public function forStaff(Request $request, $id)
    {
        $staff = User::where('role_id', User::ROLE_STAFF)->findOrFail($id);

        return response()->json(
            $this->summaryFor($staff, $request->query('month', now()->format('Y-m')))
        );
    }

    /**
     * Real-time pay view: fixed monthly base pay + the month's commission
     * ledger (10% of each completed service's price by default).
     */
    private function summaryFor(User $staff, string $month): array
    {
        [$year, $mon] = array_pad(explode('-', $month), 2, null);

        $entries = Commission::where('user_id', $staff->id)
            ->when($year && $mon, function ($query) use ($year, $mon) {
                $query->whereYear('earned_at', $year)->whereMonth('earned_at', $mon);
            })
            ->with('appointment.service:id,name')
            ->orderByDesc('earned_at')
            ->get();

        $basePay = (float) ($staff->staffProfile?->base_pay ?? 0);
        $commissionTotal = round((float) $entries->sum('amount'), 2);

        return [
            'month' => $month,
            'staff' => ['id' => $staff->id, 'name' => $staff->name],
            'base_pay' => number_format($basePay, 2, '.', ''),
            'commission_rate' => (string) ($staff->staffProfile?->commission_rate ?? '10.00'),
            'commission_total' => number_format($commissionTotal, 2, '.', ''),
            'grand_total' => number_format($basePay + $commissionTotal, 2, '.', ''),
            'entries' => $entries,
        ];
    }
}
