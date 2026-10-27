<?php

namespace App\Support;

use App\Models\LeaveAdjustment;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LeaveBalances
{
    public const ANNUAL_SL = 12;
    public const ANNUAL_VL = 12;

    /**
     * Get leave balances for a collection of staff/manager users for a given
     * year.
     *
     * Remaining = max(0, 12 - approved days consumed + owner adjustments),
     * where adjustments are the signed rows in leave_adjustments for that
     * user, type and year. The consumed figure itself is never overwritten.
     */
    public static function forUsers(Collection $users, ?int $year = null): array
    {
        $year = $year ?? Carbon::now()->year;
        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($year, 12, 31)->endOfDay();

        // Get all approved leave requests for these users that fall in this year
        $userIds = $users->pluck('id')->all();

        $requests = LeaveRequest::whereIn('user_id', $userIds)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where(function ($query) use ($startOfYear, $endOfYear) {
                $query->whereBetween('start_date', [$startOfYear, $endOfYear])
                    ->orWhereBetween('end_date', [$startOfYear, $endOfYear])
                    ->orWhere(function ($query) use ($startOfYear, $endOfYear) {
                        $query->where('start_date', '<=', $startOfYear)
                            ->where('end_date', '>=', $endOfYear);
                    });
            })
            ->get();

        // Owner corrections for this year, grouped per user. Signed rows:
        // negative reduces remaining, positive adds back.
        $adjustments = LeaveAdjustment::whereIn('user_id', $userIds)
            ->where('year', $year)
            ->get()
            ->groupBy('user_id');

        $balances = [];

        foreach ($users as $user) {
            $slUsed = 0;
            $vlUsed = 0;

            $userRequests = $requests->where('user_id', $user->id);

            foreach ($userRequests as $request) {
                // Skip emergency and other types - they don't touch balances
                if (!in_array($request->type, ['sick', 'vacation'], true)) {
                    continue;
                }

                // Calculate days consumed in this year (inclusive counting)
                $reqStart = $request->start_date instanceof Carbon ? $request->start_date : Carbon::parse($request->start_date);
                $reqEnd = $request->end_date instanceof Carbon ? $request->end_date : Carbon::parse($request->end_date);

                // Clip to year boundaries for counting
                $countStart = $reqStart->copy();
                $countEnd = $reqEnd->copy();

                if ($countStart->year < $year) {
                    $countStart = Carbon::create($year, 1, 1);
                }
                if ($countEnd->year > $year) {
                    $countEnd = Carbon::create($year, 12, 31);
                }

                if ($countStart->gt($countEnd)) {
                    continue;
                }

                // Days are counted inclusively
                $days = $countStart->diffInDays($countEnd) + 1;

                if ($request->type === 'sick') {
                    $slUsed += $days;
                } elseif ($request->type === 'vacation') {
                    $vlUsed += $days;
                }
            }

            $slAdjustment = (int) ($adjustments->get($user->id)?->where('type', LeaveAdjustment::TYPE_SICK)->sum('days') ?? 0);
            $vlAdjustment = (int) ($adjustments->get($user->id)?->where('type', LeaveAdjustment::TYPE_VACATION)->sum('days') ?? 0);

            $slRemaining = max(0, self::ANNUAL_SL - $slUsed + $slAdjustment);
            $vlRemaining = max(0, self::ANNUAL_VL - $vlUsed + $vlAdjustment);

            $balances[$user->id] = [
                'sl_used' => $slUsed,
                'sl_remaining' => $slRemaining,
                'sl_adjustment' => $slAdjustment,
                'vl_used' => $vlUsed,
                'vl_remaining' => $vlRemaining,
                'vl_adjustment' => $vlAdjustment,
            ];
        }

        return $balances;
    }

    /**
     * Get balances for a single user.
     */
    public static function forUser(User $user, ?int $year = null): array
    {
        $result = self::forUsers(collect([$user]), $year);
        return $result[$user->id] ?? [
            'sl_used' => 0,
            'sl_remaining' => self::ANNUAL_SL,
            'sl_adjustment' => 0,
            'vl_used' => 0,
            'vl_remaining' => self::ANNUAL_VL,
            'vl_adjustment' => 0,
        ];
    }
}
