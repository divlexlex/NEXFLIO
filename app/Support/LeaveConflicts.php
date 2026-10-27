<?php

namespace App\Support;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Booking conflicts for a leave request: the staff member's OWN active
 * appointments (assigned as personnel) whose date falls inside the requested
 * leave range.
 *
 * "Active" = anything still expected to happen: Unverified, Booked and
 * In-Service. Cancelled, No-Show and Completed bookings never conflict.
 *
 * Conflicts are advisory only — they are shown to the staff member before
 * filing and to the approver during review; they never block a filing.
 */
class LeaveConflicts
{
    /**
     * Statuses that still occupy the staff member's calendar.
     *
     * @return array<int, AppointmentStatus>
     */
    public static function activeStatuses(): array
    {
        return [
            AppointmentStatus::Unverified,
            AppointmentStatus::Booked,
            AppointmentStatus::InService,
        ];
    }

    /**
     * The user's own active appointments between two dates (inclusive).
     */
    public static function forUser(int $userId, string $startDate, string $endDate): Collection
    {
        return self::forUsers([$userId], $startDate, $endDate);
    }

    /**
     * Active appointments for several users inside one date window.
     */
    public static function forUsers(array $userIds, string $startDate, string $endDate): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        return Appointment::query()
            ->whereIn('personnel_id', $userIds)
            ->whereDate('appointment_date', '>=', $startDate)
            ->whereDate('appointment_date', '<=', $endDate)
            ->whereIn('status', self::activeStatuses())
            ->with(['service:id,name', 'user:id,name'])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Conflict rows for a page of leave requests, keyed by leave id.
     *
     * One query for the whole page; each leave is then matched against its
     * own user's appointments inside its own range.
     *
     * @param  Collection<int, \App\Models\LeaveRequest>  $leaves
     * @return array<int, array<int, array<string, string>>>
     */
    public static function forLeaves(Collection $leaves): array
    {
        $userIds = $leaves->pluck('user_id')->unique()->filter()->values()->all();

        if ($userIds === []) {
            return [];
        }

        $starts = $leaves->map(fn ($leave) => $leave->start_date->toDateString())->sort()->values();
        $ends = $leaves->map(fn ($leave) => $leave->end_date->toDateString())->sort()->values();

        $byUser = self::forUsers($userIds, $starts->first(), $ends->last())->groupBy('personnel_id');

        $result = [];

        foreach ($leaves as $leave) {
            $start = $leave->start_date->toDateString();
            $end = $leave->end_date->toDateString();

            $matching = ($byUser->get($leave->user_id) ?? collect())
                ->filter(function (Appointment $appointment) use ($start, $end) {
                    $date = $appointment->appointment_date->toDateString();

                    return $date >= $start && $date <= $end;
                })
                ->values();

            $result[$leave->id] = self::describe($matching);
        }

        return $result;
    }

    /**
     * Normalise appointments into the rows the views render: date, start
     * time, service and client name.
     *
     * @return array<int, array<string, string>>
     */
    public static function describe(Collection $appointments): array
    {
        return $appointments->map(fn (Appointment $appointment) => [
            'date' => $appointment->appointment_date->toDateString(),
            'date_label' => $appointment->appointment_date->format('M j, Y'),
            'time_label' => Carbon::parse($appointment->start_time)->format('g:i A'),
            'service' => $appointment->displayServiceName(),
            'client' => $appointment->clientName() ?? 'Client',
        ])->all();
    }
}
