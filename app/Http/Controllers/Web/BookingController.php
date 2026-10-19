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
use App\Support\BookingExtras;
use App\Support\PackageStaffSelection;
use App\Support\ServiceCapacity;
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
        $service = Service::bookableAtBranch()->with(['variants', 'positions'])->findOrFail($id);

        // Extra services the client may add to the same visit: real active
        // branch-bookable REGULAR services (never packages). Packages stay a
        // main-service choice only, exactly like the walk-in extras rule.
        $extraServices = Service::bookableAtBranch()
            ->with('variants')
            ->orderBy('name')
            ->get()
            ->reject(fn (Service $candidate) => $candidate->isPackageForExtras())
            ->map(fn (Service $candidate) => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'category' => $candidate->category,
                'price' => (float) $candidate->price,
                'down_payment' => $candidate->down_payment !== null ? (float) $candidate->down_payment : null,
                'duration_minutes' => (int) $candidate->duration_minutes,
                // Position names that may perform it — the same
                // category→position mapping the main service uses.
                'positions' => Position::namesForServiceCategory((string) $candidate->category),
                'variants' => $candidate->variants->map(fn ($variant) => [
                    'label' => $variant->label,
                    'price' => (float) $variant->price,
                ])->values(),
            ])
            ->values();

        // Eligibility pool for the extras' personnel picks — the same
        // category→position mapping the main service uses, filtered to
        // active, non-break staff. Date-aware availability is applied on the
        // schedule step, where the date is known.
        $staffCatalog = User::where('role_id', User::ROLE_STAFF)
            ->whereHas('staffProfile', fn ($query) => $query
                ->where('employment_status', 'active')
                ->where('is_on_break', false))
            ->with('staffProfile:id,user_id,position')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'position' => $member->staffProfile?->position,
            ])
            ->values();

        return view('account.booking.service-detail', [
            'service' => $service,
            'extraServices' => $extraServices,
            'staffCatalog' => $staffCatalog,
            'eligiblePositionNames' => Position::namesForServiceCategory($service->category),
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

        $extras = $this->extrasFromRequest($request);

        return view('account.booking.schedule', $this->scheduleViewData($request, $service, $date, $variant, $extras));
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

        $extras = $this->extrasFromRequest($request);

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.branch.schedule', $extras);
        if ($scheduleError) {
            return $scheduleError;
        }

        $staff = PackageStaffSelection::fromRequest($request, $service);

        return view('account.booking.details', [
            'service' => $service,
            'variant' => $variant,
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'staff' => $staff,
            'personnelLabel' => $this->personnelLabelFor($service, $request),
            'client' => Auth::user(),
            'notes' => $request->query('notes'),
            'extras' => $extras,
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

        $extras = $this->extrasFromRequest($request);

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.branch.schedule', $extras);
        if ($scheduleError) {
            return $scheduleError;
        }

        $staff = PackageStaffSelection::fromRequest($request, $service);

        return view('account.booking.review', [
            'service' => $service,
            'variant' => $variant,
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'staff' => $staff,
            'personnelLabel' => $this->personnelLabelFor($service, $request),
            'notes' => $request->query('notes'),
            'client' => Auth::user(),
            'extras' => $extras,
            ...$this->feeQuote($service, $variant, $extras),
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

        $extras = $this->extrasFromRequest($request);

        $scheduleError = $this->validateScheduleSelection($request, 'account.booking.branch.schedule', $extras);
        if ($scheduleError) {
            return $scheduleError;
        }

        $staff = PackageStaffSelection::fromRequest($request, $service);

        return view('account.booking.payment', [
            'service' => $service,
            'variant' => $variant,
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'staff' => $staff,
            'notes' => $request->query('notes'),
            'extras' => $extras,
            ...$this->feeQuote($service, $variant, $extras),
        ]);
    }

    public function store(StoreWebBranchBookingRequest $request, NotificationService $notificationService)
    {
        $validated = $request->validated();

        // Package tier (when the service has variants) resolved server-side
        // from the DB — the browser only ever sends an id. A service without
        // variants bills its listed price exactly as before.
        $service = Service::with(['variants', 'positions'])->findOrFail($validated['service_id']);
        $variant = isset($validated['variant_id'])
            ? $service->variants->firstWhere('id', (int) $validated['variant_id'])
            : null;
        $mainPrice = (float) ($variant?->price ?? $service->price);

        // Extra services in the SAME visit. Re-resolved from the DB here (the
        // request already validated them) so prices are never taken from the
        // browser: variant price when a variant is chosen, else service price.
        $extras = BookingExtras::resolve($validated['extras'] ?? []);
        $extrasTotal = BookingExtras::totalPrice($extras);
        $price = round($mainPrice + $extrasTotal, 2);

        // The down payment is always re-derived here — a browser-supplied
        // amount is never trusted. Each booked service contributes its own
        // configured down payment, capped at its resolved price; a service
        // with none contributes 0. Cash is only allowed when nothing is due
        // online; otherwise the down payment is paid online with proof.
        $method = $validated['method'] ?? 'gcash';
        $isCash = $method === 'cash';
        $fee = BookingExtras::downPayment($service, $mainPrice, $extras);
        $packageAssignments = $validated['package_assignments'] ?? null;

        $appointment = DB::transaction(function () use ($request, $validated, $service, $variant, $price, $extras, $method, $isCash, $fee, $packageAssignments) {
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

            if (is_array($packageAssignments)) {
                $appointment->syncPersonnelAssignments($packageAssignments);
            }

            // Each extra is an appointment_added_services row under the SAME
            // Appointment ID — one appointment, one reference code. Its
            // price is already folded into booked_price above; source
            // 'booking' marks it as part of the client's original avail (not
            // a later Payments add).
            foreach ($extras as $extra) {
                $appointment->addedServices()->create([
                    'service_id' => $extra['service']->id,
                    'variant_label' => $extra['variant_label'],
                    'price' => $extra['price'],
                    'personnel_id' => $extra['personnel_id'],
                    'added_by' => $request->user()->id,
                    'source' => 'booking',
                ]);
            }

            // Online with a down payment => only that amount is charged now
            // (the balance settles at the branch). Online with no down
            // payment => the full booked price is paid online. Cash => the
            // full booked price is owed at the branch; the row stays pending
            // until the branch records it — pending cash never counts as paid
            // (see Appointment::paymentState()).
            $paymentData = [
                'amount' => $isCash ? $price : ($fee > 0 ? $fee : $price),
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
        } elseif ($fee > 0) {
            $notifyTitle = 'Booking received';
            $notifyBody = "We received your booking for {$appointment->displayServiceName()}. "
                . 'Your down payment of ₱'.number_format($fee, 2).' is pending verification — '
                . 'the ₱'.number_format($price - $fee, 2).' balance is payable at the branch.';
        } else {
            $notifyTitle = 'Booking received';
            $notifyBody = "We received your booking for {$appointment->displayServiceName()}. "
                . 'Your payment of ₱'.number_format($price, 2).' is pending verification.';
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
        $appointment = Appointment::with(['service', 'personnel', 'payment', 'address', 'addedServices.service'])
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

        return view('account.booking.home.schedule', array_merge(
            $this->scheduleViewData($request, $service, $date),
            ['address' => $this->addressFieldsFromRequest($request)]
        ));
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

        $staff = PackageStaffSelection::fromRequest($request, $service);

        return view('account.booking.home.details', [
            'service' => $service,
            'address' => $this->addressFieldsFromRequest($request),
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'staff' => $staff,
            'personnelLabel' => $this->personnelLabelFor($service, $request),
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

        $staff = PackageStaffSelection::fromRequest($request, $service);

        return view('account.booking.home.review', [
            'service' => $service,
            'address' => $this->addressFieldsFromRequest($request),
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'staff' => $staff,
            'personnelLabel' => $this->personnelLabelFor($service, $request),
            'notes' => $request->query('notes'),
            'client' => Auth::user(),
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

        $staff = PackageStaffSelection::fromRequest($request, $service);

        return view('account.booking.home.payment', [
            'service' => $service,
            'address' => $this->addressFieldsFromRequest($request),
            'date' => $request->query('date'),
            'time' => $request->query('time'),
            'personnel' => $request->query('personnel'),
            'staff' => $staff,
            'notes' => $request->query('notes'),
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

        // Same down-payment derivation as the Branch flow: the configured
        // per-service amount, capped at the resolved booked price; none means
        // 0. Cash is only allowed when nothing is due online.
        $service = Service::with(['variants', 'positions'])->findOrFail($validated['service_id']);
        $variant = isset($validated['variant_id'])
            ? $service->variants->firstWhere('id', (int) $validated['variant_id'])
            : null;
        $price = (float) ($variant?->price ?? $service->price);

        $method = $validated['method'] ?? 'gcash';
        $isCash = $method === 'cash';
        $fee = BookingExtras::downPaymentForService($service, $price);
        $packageAssignments = $validated['package_assignments'] ?? null;

        $appointment = DB::transaction(function () use ($request, $validated, $service, $variant, $price, $method, $isCash, $fee, $packageAssignments) {
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

            if (is_array($packageAssignments)) {
                $appointment->syncPersonnelAssignments($packageAssignments);
            }

            // Online with a down payment => only that amount is charged now
            // (the balance settles later). Online with none => the full
            // booked price is paid online. Cash => the full booked price is
            // owed at the door; the row stays pending until recorded —
            // pending cash never counts as paid (see
            // Appointment::paymentState()).
            $paymentData = [
                'amount' => $isCash ? $price : ($fee > 0 ? $fee : $price),
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
                : ($fee > 0
                    ? "We received your Home Service booking for {$appointment->displayServiceName()}. "
                        . 'Your down payment of ₱'.number_format($fee, 2).' is pending verification — '
                        . 'the ₱'.number_format($price - $fee, 2).' balance is payable at the branch.'
                    : "We received your Home Service booking for {$appointment->displayServiceName()}. "
                        . 'Your payment of ₱'.number_format($price, 2).' is pending verification.'),
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
            ? Service::with(['variants', 'positions'])
                ->where('status', 'active')
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
    private function validateScheduleSelection(Request $request, string $scheduleRoute, array $extras = [])
    {
        $date = $request->query('date');
        $time = $request->query('time');
        $personnel = $request->query('personnel');
        $serviceId = $request->query('service');
        $service = $serviceId
            ? Service::with('positions')->find($serviceId)
            : null;
        $extraDuration = array_sum(array_map(fn (array $extra) => (int) $extra['service']->duration_minutes, $extras));

        try {
            $request->validate([
                'date' => 'required|date|after_or_equal:tomorrow',
                'time' => 'required|date_format:H:i',
            ], [], [
                'date' => 'date', 'time' => 'time',
            ]);

            // The whole visit (main + extras) must fit inside the shop's
            // closing time — the same window rule store() enforces. Only
            // checked when extras are present, so a single-service booking
            // (and the Home flow, which never has extras) behaves exactly as
            // before.
            if ($extras !== [] && $service && ! BusinessHours::startFitsOnDate(
                (string) $date,
                (string) $time,
                (int) ($service->duration_minutes ?? 0) + $extraDuration
            )) {
                throw ValidationException::withMessages([
                    'time' => 'That time is past shop closing. Please choose an earlier slot, or book for another day.',
                ]);
            }

            if ($service?->requiresMultiStaff()) {
                $staff = PackageStaffSelection::fromRequest($request, $service);
                if (! PackageStaffSelection::isComplete($service, $staff)) {
                    throw ValidationException::withMessages([
                        'staff' => 'Please choose Spa Personnel for every role in this package.',
                    ]);
                }

                foreach ($staff as $positionId => $value) {
                    if ($value === 'any') {
                        continue;
                    }
                    $eligible = $this->eligiblePersonnel($date, $service, [$service->positions->firstWhere('id', $positionId)?->name])
                        ->pluck('id');
                    if (! $eligible->contains((int) $value)) {
                        throw ValidationException::withMessages([
                            'staff' => 'That personnel selection is no longer available for this date.',
                        ]);
                    }
                }
            } else {
                $request->validate([
                    'personnel' => 'required',
                ], [], ['personnel' => 'personnel']);

                if ($personnel !== 'any') {
                    $eligible = $this->eligiblePersonnel($date, $service)->pluck('id');
                    if (! $eligible->contains((int) $personnel)) {
                        throw ValidationException::withMessages([
                            'personnel' => 'That personnel selection is no longer available for this date.',
                        ]);
                    }
                }
            }
        } catch (ValidationException $e) {
            return redirect()
                ->route($scheduleRoute, $this->carryAddress($request, array_merge([
                    'service' => $request->query('service'),
                    'variant' => $request->query('variant'),
                ], $request->query('staff') ? ['staff' => $request->query('staff')] : [])))
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
    /**
     * Shared schedule payload for Branch + Home. Packages get one staff
     * picker per required position; regular services keep the flat list.
     *
     * @return array<string, mixed>
     */
    private function scheduleViewData(Request $request, Service $service, string $date, ?ServiceVariant $variant = null, array $extras = []): array
    {
        $eligiblePersonnel = $this->eligiblePersonnel($date, $service);
        $eligibleIds = $eligiblePersonnel->pluck('id');
        $isMultiStaff = $service->requiresMultiStaff();
        $extraDuration = array_sum(array_map(fn (array $extra) => (int) $extra['service']->duration_minutes, $extras));

        $personnelParam = $request->query('personnel'); // null | 'any' | numeric id
        $staffSelection = PackageStaffSelection::fromRequest($request, $service);
        $eligibleByPosition = collect();
        $capacityGuests = $variant?->guests;

        if ($isMultiStaff) {
            foreach ($service->positions as $position) {
                $pool = $this->eligiblePersonnel($date, $service, [$position->name]);
                $eligibleByPosition->put($position->id, $pool);

                $pick = $staffSelection[$position->id] ?? null;
                if ($pick && $pick !== 'any' && ! $pool->contains('id', (int) $pick)) {
                    unset($staffSelection[$position->id]);
                }
            }

            $slots = [];
            if (PackageStaffSelection::isComplete($service, $staffSelection)) {
                $concrete = PackageStaffSelection::concreteIds($staffSelection);
                $slots = $concrete === []
                    ? $this->buildSlotGrid($service, $date, 'any', $eligibleIds, $capacityGuests, $extraDuration)
                    : $this->buildSlotGridForStaff($service, $date, $concrete, $capacityGuests, $extraDuration);
            }

            // Carry a sentinel so downstream steps that still read `personnel`
            // (legacy links) treat a complete package pick as ready.
            $personnelParam = PackageStaffSelection::isComplete($service, $staffSelection)
                ? (PackageStaffSelection::primaryPersonnelId($staffSelection) !== null
                    ? (string) PackageStaffSelection::primaryPersonnelId($staffSelection)
                    : 'any')
                : null;
        } else {
            if ($personnelParam && $personnelParam !== 'any' && ! $eligibleIds->contains((int) $personnelParam)) {
                $personnelParam = null;
            }

            $slots = [];
            if ($personnelParam) {
                $slots = $this->buildSlotGrid($service, $date, $personnelParam, $eligibleIds, $capacityGuests, $extraDuration);
            }
        }

        return [
            'service' => $service,
            'variant' => $variant,
            'date' => $date,
            'minDate' => $this->minDate(),
            'maxDate' => now()->addDays(self::BOOKING_WINDOW_DAYS)->toDateString(),
            'personnelParam' => $personnelParam,
            'staffSelection' => $staffSelection,
            'isMultiStaff' => $isMultiStaff,
            'eligiblePersonnel' => $eligiblePersonnel,
            'eligibleByPosition' => $eligibleByPosition,
            'slots' => $slots,
            'selectedTime' => $request->query('time'),
            'extras' => $extras,
            'extraDuration' => $extraDuration,
            'totalDuration' => (int) ($service->duration_minutes ?? 0) + $extraDuration,
        ];
    }

    /**
     * Slot grid where EVERY listed staff id must be free (package multi-pick).
     *
     * @param  list<int>  $userIds
     */
    private function buildSlotGridForStaff(Service $service, string $date, array $userIds, ?int $guests = null, int $extraDuration = 0): array
    {
        $grids = [];
        foreach ($userIds as $userId) {
            $grids[] = collect($this->buildSlotGrid($service, $date, (string) $userId, collect($userIds), $guests, $extraDuration))
                ->keyBy('time');
        }

        $first = $grids[0] ?? collect();

        return $first->map(function (array $slot, string $time) use ($grids) {
            $available = collect($grids)->every(
                fn ($grid) => (bool) ($grid->get($time)['available'] ?? false)
            );

            return ['time' => $time, 'available' => $available];
        })->values()->all();
    }

    private function personnelLabelFor(Service $service, Request $request): string
    {
        if ($service->requiresMultiStaff()) {
            return PackageStaffSelection::label(
                PackageStaffSelection::fromRequest($request, $service),
                $service->positions
            );
        }

        return $this->personnelLabel($request->query('personnel'));
    }

    private function eligiblePersonnel(string $date, ?Service $service = null, ?array $positionNames = null)
    {
        $dayOfWeek = (int) Carbon::parse($date)->dayOfWeek; // 0=Sun

        // Staff filter: Packages use the explicit multi-position list on the
        // service (Facial + Massage packages need both techs). Everything
        // else still maps category → positions via the positions table; a
        // category WITH linked positions shows only those staff, a category
        // with NONE keeps the no-filter behaviour below.
        // Callers can pass $positionNames to narrow to one role (package UI).
        if ($positionNames === null) {
            if ($service?->category === 'Packages') {
                $positionNames = $service->relationLoaded('positions')
                    ? $service->positions->pluck('name')->all()
                    : $service->positions()->pluck('positions.name')->all();
            } else {
                $positionNames = $service?->category
                    ? Position::namesForServiceCategory($service->category)
                    : [];
            }
        }

        $positionNames = array_values(array_filter($positionNames));

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
    private function buildSlotGrid(Service $service, string $date, string $personnelParam, $eligibleIds, ?int $guests = null, int $extraDuration = 0): array
    {
        $duration = $service->duration_minutes ?: 30;
        // The whole visit — main service plus every extra — is what has to
        // fit inside business hours and inside a blocked window. Capacity
        // pools stay per service (the main service's own duration), exactly
        // as before.
        $visitDuration = $duration + max(0, $extraDuration);
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

        // Busy from the legacy personnel_id column OR any package
        // appointment_personnel row pointing at the same staff.
        $takenRows = Appointment::query()
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->where(function ($query) use ($candidateIds) {
                $query->whereIn('personnel_id', $candidateIds)
                    ->orWhereHas('personnelAssignments', function ($assignments) use ($candidateIds) {
                        $assignments->whereIn('user_id', $candidateIds);
                    });
            })
            ->with(['personnelAssignments' => fn ($query) => $query->whereIn('user_id', $candidateIds)])
            ->get(['id', 'personnel_id', 'start_time']);

        $taken = collect();
        foreach ($takenRows as $row) {
            $time = substr((string) $row->start_time, 0, 5);
            $ids = collect([$row->personnel_id])
                ->merge($row->personnelAssignments->pluck('user_id'))
                ->filter()
                ->unique();
            foreach ($ids as $pid) {
                if (! $candidateIds->contains($pid)) {
                    continue;
                }
                $taken[$pid] = array_values(array_unique(array_merge($taken->get($pid, []), [$time])));
            }
        }

        $now = now();
        $isToday = $date === $now->toDateString();

        $slots = [];
        for ($minute = $openMinute; $minute < 1440; $minute += self::SLOT_STEP_MINUTES) {
            // The ONE shared last-call rule (no start at/after 12:00 AM; the
            // service must finish by the overnight-aware close).
            if (! BusinessHours::slotStartAllowed($minute, $visitDuration, $openMinute, $effectiveClose)) {
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
            $isBlocked = $blockedSlots->contains(function ($b) use ($minute, $visitDuration, $candidateIds) {
                $slotEnd = $minute + $visitDuration;
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

            // Resource beds/rooms (massage 2, head spa 4, esthetics 1):
            // hide the slot when the service's pool is already full.
            if ($available && ! ServiceCapacity::hasCapacity($service, $date, $label, $guests)) {
                $available = false;
            }

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
     * price, the per-service down payment due now (each configured amount
     * capped at its own resolved price), and the balance payable at the
     * branch. Display only — store() recomputes all of this from scratch.
     *
     * With extras, `price` is the whole visit (main + extras) and `fee` is
     * the sum of each booked service's own down payment — the same
     * computation store() uses for the single booking payment.
     *
     * @param  list<array{service: Service, variant_label: string|null, price: float, down_payment: float, personnel_id: int|null}>  $extras
     * @return array{price: float, fee: float, balance: float, mainPrice: float, mainFee: float, extrasTotal: float, extrasDownPayment: float, requiresDownPayment: bool}
     */
    private function feeQuote(Service $service, ?ServiceVariant $variant, array $extras = []): array
    {
        $mainPrice = (float) ($variant?->price ?? $service->price);
        $extrasTotal = BookingExtras::totalPrice($extras);
        $price = round($mainPrice + $extrasTotal, 2);
        $mainFee = BookingExtras::downPaymentForService($service, $mainPrice);
        $extrasDownPayment = round(array_sum(array_map(
            fn (array $extra) => BookingExtras::downPaymentForService($extra['service'], (float) $extra['price']),
            $extras
        )), 2);
        $fee = round($mainFee + $extrasDownPayment, 2);

        return [
            'price' => $price,
            'fee' => $fee,
            'balance' => max(0, round($price - $fee, 2)),
            'mainPrice' => $mainPrice,
            'mainFee' => $mainFee,
            'extrasTotal' => $extrasTotal,
            'extrasDownPayment' => $extrasDownPayment,
            'requiresDownPayment' => $fee > 0,
        ];
    }

    /**
     * Extras carried through the wizard's query string. Display-only at this
     * stage: an unparseable/invalid row is simply dropped, never trusted —
     * store() re-resolves everything from the DB. Rows are carried as
     * extras[N][service_id|variant_label|personnel_id].
     *
     * @return list<array{service: Service, variant_label: string|null, price: float, personnel_id: int|null}>
     */
    private function extrasFromRequest(Request $request): array
    {
        $rows = $request->query('extras', []);
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $extras = [];
        foreach (array_values($rows) as $row) {
            if (! is_array($row) || empty($row['service_id'])) {
                continue;
            }

            try {
                $extras[] = BookingExtras::resolveRow($row, count($extras));
            } catch (ValidationException) {
                // A tampered/removed extra never breaks the step; store()
                // is the authoritative gate.
                continue;
            }
        }

        return $extras;
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
