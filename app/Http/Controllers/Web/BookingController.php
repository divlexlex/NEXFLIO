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
use App\Models\Position;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\StaffSchedule;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\ReservationFee;
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
     * Slot grid granularity. The actual open/close window comes from the
     * business_hours table per day (see App\Models\BusinessHours::
     * windowForDay / slotStartAllowed) — there is no hardcoded default here.
     */
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
        $service = Service::bookableAtBranch()->with('variants')->findOrFail($id);

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

        $variant = $this->resolveVariant($request, $service);
        if ($variant instanceof \Illuminate\Http\RedirectResponse) {
            return $variant;
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
            'variant' => $variant,
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

        $variant = $this->resolveVariant($request, $service);
        if ($variant instanceof \Illuminate\Http\RedirectResponse) {
            return $variant;
        }

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.branch.schedule');
        if ($scheduleError) {
            return $scheduleError;
        }

        return view('account.booking.details', [
            'service' => $service,
            'variant' => $variant,
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

        $variant = $this->resolveVariant($request, $service);
        if ($variant instanceof \Illuminate\Http\RedirectResponse) {
            return $variant;
        }

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.branch.schedule');
        if ($scheduleError) {
            return $scheduleError;
        }

        return view('account.booking.review', [
            'service' => $service,
            'variant' => $variant,
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'personnelLabel' => $this->personnelLabel($request->query('personnel')),
            'notes' => $request->query('notes'),
            'client' => Auth::user(),
            // Packages (services with variants) never offer Cash — the
            // reservation fee must be paid online with proof.
            'isPackage' => $service->variants->isNotEmpty(),
            ...$this->feeQuote($service, $variant),
        ]);
    }

    public function payment(Request $request)
    {
        $service = $this->requireService($request, ServiceLocationType::bookableAtBranch(), 'account.booking.branch.service');
        if ($service instanceof \Illuminate\Http\RedirectResponse) {
            return $service;
        }

        $variant = $this->resolveVariant($request, $service);
        if ($variant instanceof \Illuminate\Http\RedirectResponse) {
            return $variant;
        }

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.branch.schedule');
        if ($scheduleError) {
            return $scheduleError;
        }

        return view('account.booking.payment', [
            'service' => $service,
            'variant' => $variant,
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'notes' => $request->query('notes'),
            // Packages (services with variants) never offer Cash — the
            // reservation fee must be paid online with proof.
            'isPackage' => $service->variants->isNotEmpty(),
            ...$this->feeQuote($service, $variant),
        ]);
    }

    public function store(StoreWebBranchBookingRequest $request, NotificationService $notificationService)
    {
        $validated = $request->validated();

        // Package tier (when the service has variants) resolved server-side
        // from the DB — the browser only ever sends an id. A service without
        // variants bills its listed price exactly as before.
        $service = Service::with('variants')->findOrFail($validated['service_id']);
        $variant = isset($validated['variant_id'])
            ? $service->variants->firstWhere('id', (int) $validated['variant_id'])
            : null;
        $price = (float) ($variant?->price ?? $service->price);

        // The reservation fee is always re-derived here — a browser-supplied
        // amount is never trusted. Cash skips the fee entirely: the full
        // booked price is payable at the branch.
        $method = $validated['method'] ?? 'gcash';
        $isCash = $method === 'cash';
        $fee = ReservationFee::for($service, $variant?->label);

        $appointment = DB::transaction(function () use ($request, $validated, $service, $variant, $price, $method, $isCash, $fee) {
            $appointment = Appointment::create([
                'user_id' => $request->user()->id,
                'service_id' => $validated['service_id'],
                'variant_label' => $variant?->label,
                'booked_price' => $price,
                // Guest counts no longer drive the fee — always stored null.
                'guests' => null,
                'personnel_id' => $validated['personnel_id'],
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'notes' => $validated['notes'] ?? null,
                // Cash is a submitted request like every other method now:
                // Unverified until the branch confirms it (no proof to
                // verify in the Payments queue). The slot stays held while
                // Unverified, exactly like an online booking.
                'status' => AppointmentStatus::Unverified,
            ]);

            // Client-facing Appointment ID, assigned here (the id only
            // exists post-insert) and saved in the same transaction.
            $appointment->update(['reference_code' => $appointment->referenceCode()]);

            // Online + fee => only the down payment is charged now (the
            // balance settles at the branch). Cash => the full booked price
            // is owed at the branch; the row stays pending until the branch
            // records it — pending cash never counts as paid (see
            // Appointment::paymentState()).
            $paymentData = [
                'amount' => $isCash ? $price : $fee,
                'method' => $method,
                'status' => PaymentStatus::Pending,
            ];
            if (! $isCash && $request->hasFile('payment_proof')) {
                $paymentData['proof_path'] = $request->file('payment_proof')->store('payment_proofs', 'public');
            }
            $appointment->payment()->create($paymentData);

            return $appointment;
        });

        $appointment->load(['user', 'service', 'personnel', 'payment']);

        // Same notification used by the mobile booking path, so a Website
        // booking behaves identically to an app booking from here on — with
        // copy that matches what the client actually owes and when.
        if ($isCash) {
            $notifyTitle = 'Booking received';
            $notifyBody = "We received your Cash booking for {$appointment->displayServiceName()}. "
                . 'The branch will confirm your booking — please pay ₱'.number_format($price, 2).' at the spa.';
        } else {
            $notifyTitle = 'Booking received';
            $notifyBody = "We received your booking for {$appointment->displayServiceName()}. "
                . 'Your reservation fee of ₱'.number_format($fee, 2).' is pending verification — '
                . 'the ₱'.number_format($price - $fee, 2).' balance is payable at the branch.';
        }

        $notificationService->notify(
            $appointment->user,
            $notifyTitle,
            $notifyBody,
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
            // Packages (services with variants) never offer Cash — the
            // reservation fee must be paid online with proof.
            'isPackage' => $service->variants->isNotEmpty(),
            ...$this->feeQuote($service, null),
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
            // Packages (services with variants) never offer Cash — the
            // reservation fee must be paid online with proof.
            'isPackage' => $service->variants->isNotEmpty(),
            ...$this->feeQuote($service, null),
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

        // Same fee derivation as the Branch flow: the package tier (when the
        // service has variants), otherwise the price rule — capped at the
        // booked price. Cash skips the fee entirely: the full booked price
        // is payable at the door.
        $service = Service::with('variants')->findOrFail($validated['service_id']);
        $variant = isset($validated['variant_id'])
            ? $service->variants->firstWhere('id', (int) $validated['variant_id'])
            : null;
        $price = (float) ($variant?->price ?? $service->price);

        $method = $validated['method'] ?? 'gcash';
        $isCash = $method === 'cash';
        $fee = ReservationFee::for($service, $variant?->label);

        $appointment = DB::transaction(function () use ($request, $validated, $service, $variant, $price, $method, $isCash, $fee) {
            $appointment = Appointment::create([
                'user_id' => $request->user()->id,
                'service_id' => $validated['service_id'],
                'variant_label' => $variant?->label,
                'booked_price' => $price,
                // Guest counts no longer drive the fee — always stored null.
                'guests' => null,
                'personnel_id' => $validated['personnel_id'],
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'notes' => $validated['notes'] ?? null,
                // Cash is a submitted request like every other method now:
                // Unverified until the branch confirms it (no proof to
                // verify in the Payments queue). The slot stays held while
                // Unverified, exactly like an online booking.
                'status' => AppointmentStatus::Unverified,
            ]);

            // Client-facing Appointment ID, assigned here (the id only
            // exists post-insert) and saved in the same transaction.
            $appointment->update(['reference_code' => $appointment->referenceCode()]);

            // Online + fee => only the down payment is charged now (the
            // balance settles later). Cash => the full booked price is owed
            // at the door; the row stays pending until recorded — pending
            // cash never counts as paid (see Appointment::paymentState()).
            $paymentData = [
                'amount' => $isCash ? $price : $fee,
                'method' => $method,
                'status' => PaymentStatus::Pending,
            ];
            if (! $isCash && $request->hasFile('payment_proof')) {
                $paymentData['proof_path'] = $request->file('payment_proof')->store('payment_proofs', 'public');
            }
            $appointment->payment()->create($paymentData);

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
            $isCash
                ? "We received your Cash Home Service booking for {$appointment->displayServiceName()}. "
                    . 'The branch will confirm your booking — please pay ₱'.number_format($price, 2).' at the spa.'
                : "We received your Home Service booking for {$appointment->displayServiceName()}. "
                    . 'Your reservation fee of ₱'.number_format($fee, 2).' is pending verification — '
                    . 'the ₱'.number_format($price - $fee, 2).' balance is payable at the branch.',
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
     * Branch Booking only. When the chosen service has saleable tiers
     * (App\Models\ServiceVariant — e.g. the Head Spa packages' Solo/Couple/
     * Barkada) the wizard must carry a `variant` query param that belongs to
     * that service; a missing or foreign id bounces the Client back to the
     * service page to pick a real one. Regular services (no variants) return
     * null and every step behaves exactly as before. store() /
     * StoreWebBranchBookingRequest re-check this authoritatively from the DB.
     */
    private function resolveVariant(Request $request, Service $service): ServiceVariant|\Illuminate\Http\RedirectResponse|null
    {
        $variants = $service->variants;

        if ($variants->isEmpty()) {
            return null;
        }

        $id = $request->query('variant');
        $variant = $id !== null && $id !== ''
            ? $variants->firstWhere('id', (int) $id)
            : null;

        if (! $variant) {
            return redirect()
                ->route('account.booking.branch.service.show', $service->id)
                ->withErrors(['variant' => 'Please choose a package option to continue.']);
        }

        return $variant;
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
                'date' => 'required|date|after_or_equal:tomorrow',
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
                ->route($scheduleRoute, $this->carryAddress($request, [
                    'service' => $request->query('service'),
                    'variant' => $request->query('variant'),
                ]))
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
     * bookable staff pool the mobile app already uses. In a roster week
     * (see App\Models\StaffShiftAssignment) only rostered staff are
     * eligible; otherwise the legacy staff_schedules fallback applies
     * exactly as before.
     */
    private function eligiblePersonnel(string $date, ?Service $service = null)
    {
        $dayOfWeek = (int) Carbon::parse($date)->dayOfWeek; // 0=Sun

        // Map service category to staff positions for filtering, driven by
        // the positions table (see App\Models\Position): a category WITH
        // linked positions shows only staff holding one of them; a category
        // with NONE keeps the no-filter behaviour below.
        $positionNames = $service?->category
            ? Position::namesForServiceCategory($service->category)
            : [];

        $weekStart = StaffShiftAssignment::weekStartOf($date);
        $rosterWeek = StaffShiftAssignment::weekHasRoster($weekStart);

        $query = User::where('role_id', User::ROLE_STAFF)
            ->whereHas('staffProfile', function ($query) use ($positionNames) {
                $query->where('employment_status', 'active')
                    ->where('is_on_break', false);
                if ($positionNames !== []) {
                    $query->whereIn('position', $positionNames);
                }
            })
            ->whereDoesntHave('leaveRequests', function ($leaves) use ($date) {
                $leaves->where('status', LeaveRequest::STATUS_APPROVED)
                    ->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date);
            });

        if ($rosterWeek) {
            $query->whereIn('users.id', StaffShiftAssignment::forWeek($weekStart)->pluck('user_id'));
        } else {
            $query->where(function ($q) use ($dayOfWeek) {
                $q->whereDoesntHave('staffSchedules', function ($sq) use ($dayOfWeek) {
                    $sq->where('day_of_week', $dayOfWeek);
                })
                ->orWhereHas('staffSchedules', function ($sq) use ($dayOfWeek) {
                    $sq->where('day_of_week', $dayOfWeek)
                        ->where('is_available', true);
                });
            });
        }

        return $query
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

        // Business-hours window for this day, via the ONE shared rule
        // (App\Models\BusinessHours). Overnight-aware: a close at/before open
        // runs past midnight. Closed day → no slots.
        $window = BusinessHours::windowForDay($dayOfWeek);
        if ($window === null) {
            return []; // store closed this day
        }
        [$openMinute, $effectiveClose] = $window;

        // Apply service-level time restrictions (per-service available windows)
        // by narrowing the window; the shared start rule then governs the edge.
        $svcFrom = $service->available_from ? $this->minutesOf($service->available_from) : null;
        $svcUntil = $service->available_until ? $this->minutesOf($service->available_until) : null;
        if ($svcFrom !== null) {
            $openMinute = max($openMinute, $svcFrom);
        }
        if ($svcUntil !== null) {
            $effectiveClose = min($effectiveClose, $svcUntil);
        }

        $candidateIds = $personnelParam === 'any' ? $eligibleIds : collect([(int) $personnelParam]);

        // Per-personnel time windows. In a roster week the window is the
        // person's shift assignment; otherwise the legacy StaffSchedule
        // override applies exactly as before. The "any" case respects the
        // same windows (a slot needs at least one eligible person in-window).
        $rosterWeek = StaffShiftAssignment::weekHasRoster(StaffShiftAssignment::weekStartOf($date));
        $personnelSchedules = [];
        foreach ($candidateIds as $pid) {
            if ($rosterWeek) {
                $assignment = StaffShiftAssignment::assignmentFor((int) $pid, $date);
                if ($assignment) {
                    $personnelSchedules[$pid] = [
                        'start' => $this->minutesOf($assignment->start_time),
                        'end' => $this->minutesOf($assignment->end_time),
                    ];
                } else {
                    // No assignment that week — same as explicitly
                    // unavailable under the old override.
                    $candidateIds = $candidateIds->reject(fn ($id) => $id == $pid);
                    if ($personnelParam !== 'any') {
                        return [];
                    }
                }
                continue;
            }

            // Check staff schedules for per-personnel time overrides
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
        for ($minute = $openMinute; $minute < 1440; $minute += self::SLOT_STEP_MINUTES) {
            // The ONE shared last-call rule (no start at/after 12:00 AM; the
            // service must finish by the overnight-aware close).
            if (! BusinessHours::slotStartAllowed($minute, $duration, $openMinute, $effectiveClose)) {
                break;
            }

            $label = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
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

    /**
     * Server-side price breakdown for the Review / Payment steps: the booked
     * price, the reservation fee due now (always resolves — mapped tier,
     * custom-label fallback, or the price rule), and the balance payable at
     * the branch. Display only — store() recomputes all of this from scratch.
     *
     * @return array{price: float, fee: float, balance: float}
     */
    private function feeQuote(Service $service, ?ServiceVariant $variant): array
    {
        $price = (float) ($variant?->price ?? $service->price);
        $fee = ReservationFee::for($service, $variant?->label);

        return [
            'price' => $price,
            'fee' => $fee,
            'balance' => max(0, round($price - $fee, 2)),
        ];
    }

    /**
     * Earliest client-bookable date: tomorrow, or the next open day after
     * tomorrow if tomorrow is closed. Same-day online booking is not
     * offered (walk-ins at the counter still book same-day — see
     * AppointmentController@storeWalkIn, untouched by this rule).
     */
    private function minDate(): string
    {
        $tomorrow = now()->addDay();

        return $this->nextOpenDate($tomorrow->copy()->subDay());
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

    /** 'H:i' or 'H:i:s' clock time as minutes from midnight for slot math. */
    private function minutesOf(string $time): int
    {
        $parsed = Carbon::parse($time);

        return $parsed->hour * 60 + $parsed->minute;
    }
}
