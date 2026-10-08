<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\ServiceLocationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebBranchBookingRequest;
use App\Http\Requests\StoreWebHomeBookingRequest;
use App\Mail\BookingReceivedMail;
use App\Models\Appointment;
use App\Models\BlockedSlot;
use App\Models\BusinessHours;
use App\Models\LeaveRequest;
use App\Models\Service;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Authenticated Client Website Booking — Branch Booking (Phase 3A) and Home
 * Service Booking (Phase 3B). Every route here is behind ['auth', 'role:4']
 * in routes/web.php, the same as the rest of the /account area. Both flows
 * are thin, stateless wizards — nothing is written to the database until
 * store()/homeStore(); every step re-derives its data from the query string
 * it was given plus real DB records, never from a session or from anything
 * the browser claims about price/availability/eligibility.
 *
 * Business rules (which service is bookable where, personnel eligibility,
 * the exact-slot double-booking check, payment-proof handling, the
 * Unverified starting status) are not reinvented here — see
 * App\Http\Requests\StoreWebBranchBookingRequest and
 * StoreWebHomeBookingRequest, which mirror the mobile app's proven
 * App\Http\Requests\StoreAppointmentRequest. Home Service eligibility itself
 * is the real, server-enforced services.service_location_type column (see
 * App\Enums\ServiceLocationType) — never inferred from category text.
 */
class BookingController extends Controller
{
    /**
     * Bookable window shown in the UI. Not a server-side validation rule
     * (StoreWebBranchBookingRequest only enforces "today or later", same as
     * mobile) — just keeps the date picker and slot grid from offering an
     * unbounded future.
     */
    private const BOOKING_WINDOW_DAYS = 60;

    /**
     * Business hours: 10:00 AM – 9:00 PM.
     * Stored as minutes-from-midnight so slot math doesn't need to
     * re-parse the display string.
     */
    private const OPEN_MINUTE = 10 * 60; // 10:00 AM
    private const CLOSE_MINUTE = 21 * 60; // 9:00 PM
    private const SLOT_STEP_MINUTES = 30;

    public function start()
    {
        return view('account.booking.start');
    }

    /**
     * "Where would you like this service?" — the required client choice for
     * a `both`-eligible service (see App\Enums\ServiceLocationType), reached
     * from a Book CTA that already knows the service (catalog detail page,
     * or a card whose service supports both locations). A stale/bookmarked
     * link to a service that's since become branch-only or home-only is
     * handled gracefully by redirecting straight into that single flow
     * instead of showing a pointless one-option choice.
     */
    public function location(Request $request)
    {
        $id = $request->query('service');
        $service = $id ? Service::where('status', 'active')->find($id) : null;

        if (! $service) {
            return redirect()
                ->route('account.booking.start')
                ->withErrors(['service' => 'Please choose a service to continue.']);
        }

        return match ($service->service_location_type) {
            ServiceLocationType::Branch => redirect()->route('account.booking.branch.schedule', ['service' => $service->id]),
            ServiceLocationType::Home => redirect()->route('account.booking.home.address', ['service' => $service->id]),
            ServiceLocationType::Both => view('account.booking.location', ['service' => $service]),
        };
    }

    public function serviceIndex()
    {
        // Real, active, database-backed, branch-bookable services only —
        // nothing on this page can produce anything but a real Service id.
        $servicesByCategory = Service::bookableAtBranch()
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        return view('account.booking.service', [
            'servicesByCategory' => $servicesByCategory,
        ]);
    }

    public function serviceShow($id)
    {
        $service = Service::bookableAtBranch()->findOrFail($id);

        return view('account.booking.service-detail', [
            'service' => $service,
        ]);
    }

    public function schedule(Request $request)
    {
        $service = $this->requireService($request, ServiceLocationType::bookableAtBranch(), 'account.booking.branch.service');
        if ($service instanceof \Illuminate\Http\RedirectResponse) {
            return $service;
        }

        $date = $request->query('date') ?: now()->toDateString();
        $date = $this->clampDate($date);

        $personnelParam = $request->query('personnel'); // null | 'any' | numeric id

        $eligiblePersonnel = $this->eligiblePersonnel($date, $service);
        $eligibleIds = $eligiblePersonnel->pluck('id');

        // Drop a previously-picked personnel that's no longer eligible for
        // the (possibly changed) date, rather than silently keeping a stale
        // selection.
        if ($personnelParam && $personnelParam !== 'any' && ! $eligibleIds->contains((int) $personnelParam)) {
            $personnelParam = null;
        }

        $slots = [];
        if ($personnelParam) {
            $slots = $this->buildSlotGrid($service, $date, $personnelParam, $eligibleIds);
        }

        return view('account.booking.schedule', [
            'service' => $service,
            'date' => $date,
            'minDate' => $this->minDate(),
            'maxDate' => now()->addDays(self::BOOKING_WINDOW_DAYS)->toDateString(),
            'personnelParam' => $personnelParam,
            'eligiblePersonnel' => $eligiblePersonnel,
            'slots' => $slots,
            'selectedTime' => $request->query('time'),
        ]);
    }

    public function details(Request $request)
    {
        $service = $this->requireService($request, ServiceLocationType::bookableAtBranch(), 'account.booking.branch.service');
        if ($service instanceof \Illuminate\Http\RedirectResponse) {
            return $service;
        }

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.branch.schedule');
        if ($scheduleError) {
            return $scheduleError;
        }

        return view('account.booking.details', [
            'service' => $service,
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'personnelLabel' => $this->personnelLabel($request->query('personnel')),
            'client' => Auth::user(),
            'notes' => $request->query('notes'),
        ]);
    }

    public function review(Request $request)
    {
        $service = $this->requireService($request, ServiceLocationType::bookableAtBranch(), 'account.booking.branch.service');
        if ($service instanceof \Illuminate\Http\RedirectResponse) {
            return $service;
        }

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.branch.schedule');
        if ($scheduleError) {
            return $scheduleError;
        }

        return view('account.booking.review', [
            'service' => $service,
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'personnelLabel' => $this->personnelLabel($request->query('personnel')),
            'notes' => $request->query('notes'),
            'client' => Auth::user(),
        ]);
    }

    public function payment(Request $request)
    {
        $service = $this->requireService($request, ServiceLocationType::bookableAtBranch(), 'account.booking.branch.service');
        if ($service instanceof \Illuminate\Http\RedirectResponse) {
            return $service;
        }

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.branch.schedule');
        if ($scheduleError) {
            return $scheduleError;
        }

        return view('account.booking.payment', [
            'service' => $service,
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'notes' => $request->query('notes'),
        ]);
    }

    public function store(StoreWebBranchBookingRequest $request, NotificationService $notificationService)
    {
        $validated = $request->validated();

        $appointment = DB::transaction(function () use ($request, $validated) {
            $appointment = Appointment::create([
                'user_id' => $request->user()->id,
                'service_id' => $validated['service_id'],
                'personnel_id' => $validated['personnel_id'],
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'notes' => $validated['notes'] ?? null,
                'status' => AppointmentStatus::Unverified,
            ]);

            // Amount is always the service's listed price, taken server-side
            // — never trusted from the browser (mirrors API\AppointmentController@store).
            $appointment->payment()->create([
                'amount' => $appointment->service->price,
                'method' => $validated['method'] ?? 'gcash',
                'proof_path' => $request->file('payment_proof')->store('payment_proofs', 'public'),
                'status' => PaymentStatus::Pending,
            ]);

            return $appointment;
        });

        $appointment->load(['user', 'service', 'personnel', 'payment']);

        // Same notification used by the mobile booking path, so a Website
        // booking behaves identically to an app booking from here on.
        $notificationService->notify(
            $appointment->user,
            'Booking received',
            "We received your booking for {$appointment->service->name}. "
                . 'You will be notified once your payment is verified.',
            new BookingReceivedMail($appointment),
            ['appointment_id' => $appointment->id]
        );

        return redirect()->route('account.booking.branch.success', $appointment);
    }

    public function success($id)
    {
        // Scoped to the authenticated Client's own user_id, not a raw
        // findOrFail($id) + ownership check — a mismatched id 404s exactly
        // like a nonexistent one, so a Client can't confirm another
        // Client's appointment even exists by probing ids. Shared by both
        // Branch (account.booking.branch.success) and Home Service
        // (account.booking.home.success) routes — the view itself renders
        // the address block only when $appointment->address exists.
        $appointment = Appointment::with(['service', 'personnel', 'payment', 'address'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return view('account.booking.success', [
            'appointment' => $appointment,
        ]);
    }

    // ===== Home Service Booking (Phase 3B) ==================================

    public function homeServiceIndex()
    {
        $servicesByCategory = Service::bookableAtHome()
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        return view('account.booking.home.service', [
            'servicesByCategory' => $servicesByCategory,
        ]);
    }

    public function homeServiceShow($id)
    {
        $service = Service::bookableAtHome()->findOrFail($id);

        return view('account.booking.home.service-detail', [
            'service' => $service,
        ]);
    }

    /**
     * Home Service — Client / Address Details step. Prefills from the real,
     * database-backed Client information already implemented in Phase 2
     * (users, client_profiles, client_addresses) — never re-asked for
     * unnecessarily — but is always editable for this specific booking.
     * Changing it here never writes back to client_addresses; see
     * homeStore() and the AppointmentAddress snapshot.
     */
    public function address(Request $request)
    {
        $service = $this->requireService($request, ServiceLocationType::bookableAtHome(), 'account.booking.home.service');
        if ($service instanceof \Illuminate\Http\RedirectResponse) {
            return $service;
        }

        $user = Auth::user();
        $defaultAddress = $user->addresses()->where('is_default', true)->first();

        return view('account.booking.home.address', [
            'service' => $service,
            'client' => $user,
            'defaultAddress' => $defaultAddress,
            'sourceClientAddressId' => $request->query('source_client_address_id', $defaultAddress?->id),
            'streetAddress' => $request->query('street_address', $defaultAddress?->street_address),
            'barangay' => $request->query('barangay', $defaultAddress?->barangay),
            'cityMunicipality' => $request->query('city_municipality', $defaultAddress?->city_municipality),
            'province' => $request->query('province', $defaultAddress?->province),
            'postalCode' => $request->query('postal_code', $defaultAddress?->postal_code),
        ]);
    }

    public function homeSchedule(Request $request)
    {
        $service = $this->requireService($request, ServiceLocationType::bookableAtHome(), 'account.booking.home.service');
        if ($service instanceof \Illuminate\Http\RedirectResponse) {
            return $service;
        }

        $addressError = $this->validateAddressSelection($request);
        if ($addressError) {
            return $addressError;
        }

        $date = $request->query('date') ?: now()->toDateString();
        $date = $this->clampDate($date);

        $personnelParam = $request->query('personnel');

        $eligiblePersonnel = $this->eligiblePersonnel($date, $service);
        $eligibleIds = $eligiblePersonnel->pluck('id');

        if ($personnelParam && $personnelParam !== 'any' && ! $eligibleIds->contains((int) $personnelParam)) {
            $personnelParam = null;
        }

        $slots = [];
        if ($personnelParam) {
            $slots = $this->buildSlotGrid($service, $date, $personnelParam, $eligibleIds);
        }

        return view('account.booking.home.schedule', [
            'service' => $service,
            'address' => $this->addressFieldsFromRequest($request),
            'date' => $date,
            'minDate' => $this->minDate(),
            'maxDate' => now()->addDays(self::BOOKING_WINDOW_DAYS)->toDateString(),
            'personnelParam' => $personnelParam,
            'eligiblePersonnel' => $eligiblePersonnel,
            'slots' => $slots,
            'selectedTime' => $request->query('time'),
        ]);
    }

    public function homeDetails(Request $request)
    {
        $service = $this->requireService($request, ServiceLocationType::bookableAtHome(), 'account.booking.home.service');
        if ($service instanceof \Illuminate\Http\RedirectResponse) {
            return $service;
        }

        $addressError = $this->validateAddressSelection($request);
        if ($addressError) {
            return $addressError;
        }

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.home.schedule');
        if ($scheduleError) {
            return $scheduleError;
        }

        return view('account.booking.home.details', [
            'service' => $service,
            'address' => $this->addressFieldsFromRequest($request),
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'personnelLabel' => $this->personnelLabel($request->query('personnel')),
            'client' => Auth::user(),
            'notes' => $request->query('notes'),
        ]);
    }

    public function homeReview(Request $request)
    {
        $service = $this->requireService($request, ServiceLocationType::bookableAtHome(), 'account.booking.home.service');
        if ($service instanceof \Illuminate\Http\RedirectResponse) {
            return $service;
        }

        $addressError = $this->validateAddressSelection($request);
        if ($addressError) {
            return $addressError;
        }

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.home.schedule');
        if ($scheduleError) {
            return $scheduleError;
        }

        return view('account.booking.home.review', [
            'service' => $service,
            'address' => $this->addressFieldsFromRequest($request),
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'personnelLabel' => $this->personnelLabel($request->query('personnel')),
            'notes' => $request->query('notes'),
            'client' => Auth::user(),
        ]);
    }

    public function homePayment(Request $request)
    {
        $service = $this->requireService($request, ServiceLocationType::bookableAtHome(), 'account.booking.home.service');
        if ($service instanceof \Illuminate\Http\RedirectResponse) {
            return $service;
        }

        $addressError = $this->validateAddressSelection($request);
        if ($addressError) {
            return $addressError;
        }

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.home.schedule');
        if ($scheduleError) {
            return $scheduleError;
        }

        return view('account.booking.home.payment', [
            'service' => $service,
            'address' => $this->addressFieldsFromRequest($request),
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'notes' => $request->query('notes'),
        ]);
    }

    /**
     * Same DB::transaction()-wrapped write sequence as store() (Branch),
     * plus the AppointmentAddress snapshot — created inside the same
     * transaction, so a Home Service appointment can never exist without
     * its address, and a failure anywhere rolls back everything (no partial
     * appointment/payment/address records).
     */
    public function homeStore(StoreWebHomeBookingRequest $request, NotificationService $notificationService)
    {
        $validated = $request->validated();

        $appointment = DB::transaction(function () use ($request, $validated) {
            $appointment = Appointment::create([
                'user_id' => $request->user()->id,
                'service_id' => $validated['service_id'],
                'personnel_id' => $validated['personnel_id'],
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'notes' => $validated['notes'] ?? null,
                'status' => AppointmentStatus::Unverified,
            ]);

            $appointment->payment()->create([
                'amount' => $appointment->service->price,
                'method' => $validated['method'] ?? 'gcash',
                'proof_path' => $request->file('payment_proof')->store('payment_proofs', 'public'),
                'status' => PaymentStatus::Pending,
            ]);

            // Snapshot only — never a live reference. Editing the Client's
            // saved client_addresses row later must never change this.
            $appointment->address()->create([
                'source_client_address_id' => $validated['source_client_address_id'] ?? null,
                'street_address' => $validated['street_address'],
                'barangay' => $validated['barangay'],
                'city_municipality' => $validated['city_municipality'],
                'province' => $validated['province'],
                'postal_code' => $validated['postal_code'] ?? null,
            ]);

            return $appointment;
        });

        $appointment->load(['user', 'service', 'personnel', 'payment', 'address']);

        $notificationService->notify(
            $appointment->user,
            'Booking received',
            "We received your Home Service booking for {$appointment->service->name}. "
                . 'You will be notified once your payment is verified.',
            new BookingReceivedMail($appointment),
            ['appointment_id' => $appointment->id]
        );

        return redirect()->route('account.booking.home.success', $appointment);
    }

    // ---------------------------------------------------------------------

    /**
     * Every step after Service needs a real, active, DB-backed service from
     * the `service` query param, restricted to $allowedLocationTypes
     * (App\Enums\ServiceLocationType::bookableAtBranch()/bookableAtHome()) —
     * a Home-only service can never surface in the Branch wizard and vice
     * versa, enforced here server-side, not just by which links point where.
     * Centralized so a tampered/missing/inactive/wrong-location id always
     * sends the Client back to pick a real eligible one instead of a step
     * silently rendering with no service.
     */
    private function requireService(Request $request, array $allowedLocationTypes, string $redirectRoute)
    {
        $id = $request->query('service');
        $service = $id
            ? Service::where('status', 'active')
                ->whereIn('service_location_type', $allowedLocationTypes)
                ->find($id)
            : null;

        if (! $service) {
            return redirect()
                ->route($redirectRoute)
                ->withErrors(['service' => 'Please choose a service to continue.']);
        }

        return $service;
    }

    /**
     * Lightweight, redo-able check (not the source of truth — store() /
     * StoreWebBranchBookingRequest re-validates everything authoritatively)
     * that the date/time/personnel carried in the query string still look
     * sane, so Details/Review/Payment don't render on top of a tampered or
     * incomplete selection. $scheduleRoute lets Branch and Home Service
     * share this one check while redirecting back to their own schedule step.
     */
    private function validateScheduleSelection(Request $request, string $scheduleRoute)
    {
        $date = $request->query('date');
        $time = $request->query('time');
        $personnel = $request->query('personnel');

        try {
            $request->validate([
                'date' => 'required|date|after_or_equal:today',
                'time' => 'required|date_format:H:i',
                'personnel' => 'required',
            ], [], [
                'date' => 'date', 'time' => 'time', 'personnel' => 'personnel',
            ]);

            if ($personnel !== 'any') {
                $serviceId = $request->query('service');
                $service = $serviceId ? Service::find($serviceId) : null;
                $eligible = $this->eligiblePersonnel($date, $service)->pluck('id');
                if (! $eligible->contains((int) $personnel)) {
                    throw ValidationException::withMessages([
                        'personnel' => 'That personnel selection is no longer available for this date.',
                    ]);
                }
            }
        } catch (ValidationException $e) {
            return redirect()
                ->route($scheduleRoute, $this->carryAddress($request, ['service' => $request->query('service')]))
                ->withErrors($e->errors());
        }

        return null;
    }

    /**
     * Home Service only: lightweight check that the address fields carried
     * in the query string are still present before Schedule/Details/Review/
     * Payment render on top of them. Not authoritative — homeStore() /
     * StoreWebHomeBookingRequest validates the real, submitted values again.
     */
    private function validateAddressSelection(Request $request)
    {
        try {
            $request->validate([
                'street_address' => 'required|string',
                'barangay' => 'required|string',
                'city_municipality' => 'required|string',
                'province' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return redirect()
                ->route('account.booking.home.address', ['service' => $request->query('service')])
                ->withErrors($e->errors());
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function addressFieldsFromRequest(Request $request): array
    {
        return [
            'source_client_address_id' => $request->query('source_client_address_id'),
            'street_address' => $request->query('street_address'),
            'barangay' => $request->query('barangay'),
            'city_municipality' => $request->query('city_municipality'),
            'province' => $request->query('province'),
            'postal_code' => $request->query('postal_code'),
        ];
    }

    /**
     * Merges the address fields already sitting in the query string into a
     * redirect's params, so a validation bounce-back never silently drops
     * the address the Client already entered.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function carryAddress(Request $request, array $params): array
    {
        if (! $request->query('street_address')) {
            return $params;
        }

        return array_merge($params, $this->addressFieldsFromRequest($request));
    }

    /**
     * Mirrors API\AppointmentController@personnel exactly (active staff
     * profile, not on break, no approved leave covering the date) — the
     * bookable staff pool the mobile app already uses.
     */
    private function eligiblePersonnel(string $date, ?Service $service = null)
    {
        $dayOfWeek = (int) Carbon::parse($date)->dayOfWeek; // 0=Sun

        // Map service category to staff position for filtering
        $positionFilter = $service?->category ? match ($service->category) {
            'Facial' => \App\Enums\Position::FacialTechnician->value,
            'Massage' => \App\Enums\Position::MassageTechnician->value,
            'Nails' => \App\Enums\Position::NailTechnician->value,
            default => null, // Lashes & Brows, Aesthetics, Head Spa, Home Service → show all
        } : null;

        return User::where('role_id', User::ROLE_STAFF)
            ->whereHas('staffProfile', function ($query) use ($positionFilter) {
                $query->where('employment_status', 'active')
                    ->where('is_on_break', false);
                if ($positionFilter) {
                    $query->where('position', $positionFilter);
                }
            })
            ->whereDoesntHave('leaveRequests', function ($leaves) use ($date) {
                $leaves->where('status', LeaveRequest::STATUS_APPROVED)
                    ->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date);
            })
            ->where(function ($q) use ($dayOfWeek) {
                $q->whereDoesntHave('staffSchedules', function ($sq) use ($dayOfWeek) {
                    $sq->where('day_of_week', $dayOfWeek);
                })
                ->orWhereHas('staffSchedules', function ($sq) use ($dayOfWeek) {
                    $sq->where('day_of_week', $dayOfWeek)
                        ->where('is_available', true);
                });
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    /**
     * Candidate start times across business hours, at SLOT_STEP_MINUTES
     * granularity, filtered to whatever the selected personnel option can
     * actually support:
     *
     *  - a specific personnel id: available where that person has no
     *    existing appointment at that exact date+time (same exact-start-time
     *    collision rule as StoreAppointmentRequest/StoreWebBranchBookingRequest
     *    — this system has no duration-aware overlap check anywhere yet, see
     *    the completion report's Known Gaps).
     *  - "any": available where at least one eligible person is free at
     *    that slot.
     *
     * Past times are dropped when $date is today.
     */
    private function buildSlotGrid(Service $service, string $date, string $personnelParam, $eligibleIds): array
    {
        $duration = $service->duration_minutes ?: 30;
        $dayOfWeek = (int) Carbon::parse($date)->dayOfWeek;

        // Determine effective open/close for this day from business_hours table
        $bhours = BusinessHours::where('day_of_week', $dayOfWeek)->first();
        if (! $bhours || ! $bhours->is_open) {
            return []; // store closed this day
        }
        $openMinute = (int) Carbon::parse($bhours->open_at)->hour * 60 + (int) Carbon::parse($bhours->open_at)->minute;
        $closeMinute = (int) Carbon::parse($bhours->close_at)->hour * 60 + (int) Carbon::parse($bhours->close_at)->minute;
        // Handle overnight (close < open means spans midnight)
        if ($closeMinute <= $openMinute) {
            $closeMinute += 24 * 60;
        }
        $lastStart = $closeMinute - $duration;

        // Apply service-level time restrictions
        $svcFrom = $service->available_from ? (int) Carbon::parse($service->available_from)->hour * 60 + (int) Carbon::parse($service->available_from)->minute : null;
        $svcUntil = $service->available_until ? (int) Carbon::parse($service->available_until)->hour * 60 + (int) Carbon::parse($service->available_until)->minute : null;
        if ($svcFrom !== null && $svcFrom > $openMinute) {
            $openMinute = $svcFrom;
        }
        if ($svcUntil !== null && $svcUntil < $closeMinute) {
            $lastStart = min($lastStart, $svcUntil - $duration);
        }

        $candidateIds = $personnelParam === 'any' ? $eligibleIds : collect([(int) $personnelParam]);

        // Check staff schedules for per-personnel time overrides
        $personnelSchedules = [];
        foreach ($candidateIds as $pid) {
            $sched = StaffSchedule::where('user_id', $pid)->where('day_of_week', $dayOfWeek)->first();
            if ($sched && $sched->is_available) {
                $sStart = (int) Carbon::parse($sched->start_time)->hour * 60 + (int) Carbon::parse($sched->start_time)->minute;
                $sEnd = (int) Carbon::parse($sched->end_time)->hour * 60 + (int) Carbon::parse($sched->end_time)->minute;
                $personnelSchedules[$pid] = ['start' => $sStart, 'end' => $sEnd];
            } elseif ($sched && ! $sched->is_available) {
                // Staff explicitly unavailable this day
                $candidateIds = $candidateIds->reject(fn ($id) => $id == $pid);
                if ($personnelParam !== 'any') {
                    return [];
                }
            }
        }

        // Blocked slots for this date (all-day or time-specific)
        $blockedAllDay = BlockedSlot::where('blocked_date', $date)
            ->whereNull('personnel_id')
            ->whereNull('start_time')
            ->exists();
        if ($blockedAllDay) {
            return [];
        }
        $blockedSlots = BlockedSlot::where('blocked_date', $date)
            ->where(function ($q) use ($candidateIds) {
                $q->whereNull('personnel_id')
                    ->orWhereIn('personnel_id', $candidateIds);
            })
            ->whereNotNull('start_time')
            ->get()
            ->map(fn ($b) => [
                'personnel_id' => $b->personnel_id,
                'start' => (int) Carbon::parse($b->start_time)->hour * 60 + (int) Carbon::parse($b->start_time)->minute,
                'end' => (int) Carbon::parse($b->end_time)->hour * 60 + (int) Carbon::parse($b->end_time)->minute,
            ]);

        $taken = Appointment::query()
            ->whereIn('personnel_id', $candidateIds)
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->get(['personnel_id', 'start_time'])
            ->groupBy('personnel_id')
            ->map(fn ($rows) => $rows->pluck('start_time')->map(fn ($t) => substr($t, 0, 5))->all());

        $now = now();
        $isToday = $date === $now->toDateString();

        $slots = [];
        for ($minute = $openMinute; $minute <= $lastStart; $minute += self::SLOT_STEP_MINUTES) {
            $label = sprintf('%02d:%02d', intdiv($minute, 60) % 24, $minute % 60);
            $trueMoment = Carbon::parse($date)->startOfDay()->addMinutes($minute);

            if ($isToday && $trueMoment->lte($now)) {
                continue;
            }

            // Check if this slot falls within any personnel's specific schedule
            $personnelHasSchedule = collect($candidateIds)->contains(function ($pid) use ($minute, $personnelSchedules) {
                if (! isset($personnelSchedules[$pid])) {
                    return true; // no schedule override → uses business hours
                }
                $s = $personnelSchedules[$pid];
                return $minute >= $s['start'] && $minute < $s['end'];
            });
            if (! $personnelHasSchedule && ! empty($personnelSchedules)) {
                continue;
            }

            // Check blocked slots
            $isBlocked = $blockedSlots->contains(function ($b) use ($minute, $duration, $candidateIds) {
                $slotEnd = $minute + $duration;
                if ($b['personnel_id'] !== null) {
                    return in_array($b['personnel_id'], $candidateIds->all(), false)
                        && $minute < $b['end'] && $slotEnd > $b['start'];
                }
                return $minute < $b['end'] && $slotEnd > $b['start'];
            });
            if ($isBlocked) {
                $slots[] = ['time' => $label, 'available' => false];
                continue;
            }

            $available = $personnelParam === 'any'
                ? $candidateIds->contains(fn ($pid) => ! in_array($label, $taken->get($pid, []), true))
                : ! in_array($label, $taken->get((int) $personnelParam, []), true);

            $slots[] = ['time' => $label, 'available' => $available];
        }

        return $slots;
    }

    private function personnelLabel(?string $personnel): string
    {
        if ($personnel === 'any' || $personnel === null) {
            return 'No preferred personnel';
        }

        return User::find($personnel)?->name ?? 'Selected personnel';
    }

    private function minDate(): string
    {
        $now = now();
        $dayOfWeek = (int) $now->dayOfWeek;
        $bhours = BusinessHours::where('day_of_week', $dayOfWeek)->first();

        // If store is closed today, earliest is next open day
        if (! $bhours || ! $bhours->is_open) {
            return $this->nextOpenDate($now);
        }

        $closeMinute = (int) Carbon::parse($bhours->close_at)->hour * 60 + (int) Carbon::parse($bhours->close_at)->minute;
        if ($closeMinute >= 24 * 60) {
            $closeMinute -= 24 * 60; // wrap overnight
        }

        return $now->hour * 60 + $now->minute >= $closeMinute
            ? $this->nextOpenDate($now)
            : $now->toDateString();
    }

    private function nextOpenDate(Carbon $from): string
    {
        for ($i = 1; $i <= 7; $i++) {
            $candidate = $from->copy()->addDays($i);
            $bhours = BusinessHours::where('day_of_week', (int) $candidate->dayOfWeek)->first();
            if ($bhours && $bhours->is_open) {
                return $candidate->toDateString();
            }
        }
        return $from->addDay()->toDateString(); // fallback
    }

    private function clampDate(string $date): string
    {
        $min = $this->minDate();
        $max = now()->addDays(self::BOOKING_WINDOW_DAYS)->toDateString();

        if ($date < $min) {
            return $min;
        }
        if ($date > $max) {
            return $max;
        }

        return $date;
    }
}
