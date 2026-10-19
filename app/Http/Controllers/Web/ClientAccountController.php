<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\Gender;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentChangeRequest;
use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Notification;
use App\Models\Promo;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Authenticated Client Website area (Phase 2). Every route here is behind
 * ['auth', 'role:4'] in routes/web.php — the same CheckRole middleware the
 * existing /admin routes use, just scoped to Client instead of
 * Super Admin/Manager, so Management can never land here just by being
 * logged in. All data below is the real Appointment/User records for the
 * signed-in client — nothing on these pages is hardcoded.
 */
class ClientAccountController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = Auth::user();

        $upcoming = Appointment::with(['service', 'personnel'])
            ->where('user_id', $user->id)
            ->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::InService])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->first();

        $recentHistory = Appointment::with(['service', 'personnel'])
            ->where('user_id', $user->id)
            ->where('status', AppointmentStatus::Completed)
            ->orderByDesc('appointment_date')
            ->take(3)
            ->get();

        $pendingCount = Appointment::where('user_id', $user->id)
            ->where('status', AppointmentStatus::Unverified)
            ->count();

        // ===== Calendar widget — current (or ?month=YYYY-MM navigated) month,
        // marking every day the Client has an appointment on. Navigation is
        // plain links (no JS) so it degrades fine on very old mobile browsers.
        // A missing/garbled ?month= just falls back to the current month —
        // this whole block is a "nice to have" widget, never worth a 500 over.
        $calendarMonth = now()->startOfMonth();
        if ($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', $request->query('month'))) {
            try {
                $calendarMonth = Carbon::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfMonth();
            } catch (\Exception) {
                // keep the current-month fallback above
            }
        }

        $monthAppointments = Appointment::with('service')
            ->where('user_id', $user->id)
            ->whereBetween('appointment_date', [
                $calendarMonth->copy()->startOfMonth()->toDateString(),
                $calendarMonth->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        $appointmentsByDate = $monthAppointments->groupBy(
            fn (Appointment $appointment) => $appointment->appointment_date->toDateString()
        );

        $calendarCells = array_fill(0, $calendarMonth->dayOfWeek, null);
        for ($day = 1; $day <= $calendarMonth->daysInMonth; $day++) {
            $date = $calendarMonth->copy()->day($day);
            $calendarCells[] = [
                'day' => $day,
                'date' => $date->toDateString(),
                'isToday' => $date->isToday(),
                'hasAppointment' => $appointmentsByDate->has($date->toDateString()),
            ];
        }

        // ===== Suggested for You — active services the Client hasn't booked
        // before, so this actually reads as a suggestion rather than just
        // "everything we sell" (the Guest Home's Recommended-for-You mix).
        // Tops back up with already-tried services if there aren't 4 untried
        // ones left, so the section never renders emptier than it has to.
        $bookedServiceIds = Appointment::where('user_id', $user->id)->pluck('service_id')->filter()->unique();

        $suggestedServices = Service::where('status', 'active')
            ->when($bookedServiceIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $bookedServiceIds))
            ->inRandomOrder()
            ->take(4)
            ->get();

        if ($suggestedServices->count() < 4) {
            $suggestedServices = $suggestedServices->concat(
                Service::where('status', 'active')
                    ->whereNotIn('id', $suggestedServices->pluck('id'))
                    ->inRandomOrder()
                    ->take(4 - $suggestedServices->count())
                    ->get()
            );
        }

        $suggestions = $suggestedServices->map(fn (Service $service) => [
            'id' => $service->id,
            'title' => $service->name,
            'subtitle' => $service->category,
            'price' => $service->price,
            'image_url' => $service->image_url,
        ]);

        return view('account.dashboard', [
            'upcoming' => $upcoming,
            'recentHistory' => $recentHistory,
            'pendingCount' => $pendingCount,
            'calendarMonth' => $calendarMonth,
            'calendarCells' => $calendarCells,
            'appointmentsByDate' => $appointmentsByDate,
            'prevMonth' => $calendarMonth->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $calendarMonth->copy()->addMonth()->format('Y-m'),
            'suggestions' => $suggestions,
            // "Special for You" — same live Promos the Guest Home's Special
            // Offers section shows (App\Models\Promo::scopeLive), reused via
            // partials.offers-section so both stay visually identical.
            'promos' => Promo::live()->with('services')->latest()->get(),
        ]);
    }

    public function markNotificationRead(Request $request, int $id)
    {
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return back();
    }

    public function markAllNotificationsRead(Request $request)
    {
        Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    public function bookings()
    {
        $appointments = Appointment::with([
            'service', 'personnel', 'address', 'payment', 'payments',
            'addedServices.service',
            'pendingChangeRequest.requestedPersonnel:id,name',
            // Approved cancellation (if any) drives the cancelled-card
            // refund lines below (refund_decision + payment refund_status).
            'changeRequests' => fn ($query) => $query
                ->where('type', AppointmentChangeRequest::TYPE_CANCELLATION)
                ->where('status', AppointmentChangeRequest::STATUS_APPROVED)
                ->latest('id'),
        ])
            ->where('user_id', Auth::id())
            ->orderByDesc('appointment_date')
            ->orderByDesc('start_time')
            ->get();

        return view('account.bookings', [
            'pending' => $appointments->where('status', AppointmentStatus::Unverified)->values(),
            'upcoming' => $appointments->whereIn('status', [AppointmentStatus::Booked, AppointmentStatus::InService])->values(),
            'history' => $appointments->whereIn('status', [
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ])->values(),
            // For the "Request reschedule" modal's optional personnel picker.
            'personnel' => User::where('role_id', User::ROLE_STAFF)
                ->whereHas('staffProfile', fn ($q) => $q->where('employment_status', 'active'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Client raises a cancellation or reschedule request on their own
     * appointment. Nothing on the appointment changes here — a Manager
     * approves it in the /admin queue (AppointmentRequestController).
     */
    public function storeChangeRequest(StoreAppointmentChangeRequest $request, Appointment $appointment)
    {
        $type = $request->input('type');

        // Change requests open only once the payment is verified (Booked) —
        // Unverified bookings must wait for payment verification.
        if ($appointment->status !== AppointmentStatus::Booked) {
            return back()->withErrors(['type' => 'You can request changes once your payment has been verified.']);
        }

        AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id,
            'requested_by' => Auth::id(),
            'type' => $type,
            'status' => AppointmentChangeRequest::STATUS_PENDING,
            'reason' => $request->input('reason'),
            // Cancellation category key (config/booking.php); null for
            // reschedules and legacy rows.
            'reason_category' => $type === AppointmentChangeRequest::TYPE_CANCELLATION
                ? $request->input('reason_category')
                : null,
            'requested_date' => $type === AppointmentChangeRequest::TYPE_RESCHEDULE ? $request->input('requested_date') : null,
            'requested_start_time' => $type === AppointmentChangeRequest::TYPE_RESCHEDULE ? $request->input('requested_start_time') : null,
            'requested_personnel_id' => $type === AppointmentChangeRequest::TYPE_RESCHEDULE ? $request->input('requested_personnel_id') : null,
        ]);

        return back()->with('success', $type === AppointmentChangeRequest::TYPE_RESCHEDULE
            ? 'Reschedule request sent — the team will review it and confirm.'
            : 'Cancellation request sent — the team will review it shortly.');
    }

    /** Client withdraws their own still-pending request. */
    public function withdrawChangeRequest(Appointment $appointment, AppointmentChangeRequest $changeRequest)
    {
        abort_unless(
            $appointment->user_id === Auth::id()
                && $changeRequest->appointment_id === $appointment->id
                && $changeRequest->isPending(),
            403
        );

        $changeRequest->delete();

        return back()->with('success', 'Request withdrawn.');
    }

    /**
     * Top-up form (step 2B) for a rejected booking the client wants to
     * continue instead of refunding. Owner only; canContinueBooking() gates
     * every condition (rejected + topup-eligible + no refund in motion + no
     * live top-up + shortfall left + future slot).
     */
    public function showTopupForm(Appointment $appointment)
    {
        abort_unless($appointment->user_id === Auth::id(), 403);

        $appointment->loadMissing(['service', 'personnel', 'payment', 'payments']);

        if (! $appointment->canContinueBooking()) {
            return redirect()->route('account.bookings')
                ->withErrors(['topup' => 'This booking can no longer be continued.']);
        }

        return view('account.topup', [
            'appointment' => $appointment,
            'shortfall' => $appointment->shortfall(),
        ]);
    }

    /**
     * Submits the shortfall top-up: creates a Pending topup payment for the
     * server-recomputed shortfall (the amount is display-only on the form,
     * never trusted from the browser) and resurrects the appointment
     * Cancelled → Unverified so it rejoins the verification queue. The
     * direct status write is deliberate — terminal statuses have no normal
     * outgoing transition, and there are no payment side effects to run
     * here (the Pending topup row above is the proof awaiting review).
     */
    public function storeTopup(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->user_id === Auth::id(), 403);

        $appointment->loadMissing(['service', 'payment', 'payments']);

        if (! $appointment->canContinueBooking()) {
            return back()->withErrors(['topup' => 'This booking can no longer be continued.']);
        }

        $shortfall = $appointment->shortfall();

        $validated = $request->validate([
            'method' => [
                'required',
                Rule::in(array_diff(array_keys(config('payment.methods', [])), ['cash'])),
            ],
            'payment_proof' => 'required|image|max:5120',
        ]);

        // The slot may have been taken while the booking sat cancelled —
        // refuse instead of double-booking; the client can make a fresh
        // booking for another time.
        if ($this->slotTakenByOther($appointment)) {
            return back()->withErrors(['topup' => 'That time slot was just taken — please make a fresh booking for another time.']);
        }

        DB::transaction(function () use ($request, $appointment, $validated, $shortfall) {
            $appointment->payments()->create([
                'kind' => 'topup',
                'amount' => $shortfall,
                'method' => $validated['method'],
                'proof_path' => $request->file('payment_proof')->store('payment_proofs', 'public'),
                'status' => PaymentStatus::Pending,
            ]);

            $appointment->update(['status' => AppointmentStatus::Unverified]);
        });

        return redirect()->route('account.bookings')
            ->with('success', 'Top-up submitted — the team will verify your proof shortly.');
    }

    /**
     * The exact slot-collision check shared by the top-up and resubmit-proof
     * submissions: same personnel, same date, same start time, still held by
     * a live (non-cancelled) appointment. One implementation, used by both —
     * never a second availability check.
     */
    private function slotTakenByOther(Appointment $appointment): bool
    {
        return (bool) ($appointment->personnel_id && Appointment::query()
            ->where('personnel_id', $appointment->personnel_id)
            ->whereDate('appointment_date', $appointment->appointment_date->toDateString())
            ->where('start_time', $appointment->start_time)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->exists());
    }

    /**
     * Resubmit a clearer proof for an invalid_proof rejection: replaces the
     * rejected payment's proof, reopens it as Pending with a clean slate
     * (rejection + refund fields cleared, amount untouched — no new row),
     * and resurrects the appointment Cancelled → Unverified, the same direct
     * write the top-up submission uses (terminal statuses have no normal
     * outgoing transition, and there are no payment side effects to run).
     * Owner only; every precondition re-checked server-side.
     */
    public function resubmitProof(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->user_id === Auth::id(), 404);

        $appointment->loadMissing(['service', 'payment', 'payments']);

        $payment = $appointment->payment;
        $resubmittable = $payment !== null
            && $payment->status === PaymentStatus::Rejected
            && (bool) config('payment.rejection_reasons.'.$payment->rejection_category.'.resubmit_proof', false);

        if (! $resubmittable) {
            return back()->withErrors(['resubmit' => 'A new proof cannot be sent for this booking.']);
        }

        if ($appointment->status !== AppointmentStatus::Cancelled) {
            return back()->withErrors(['resubmit' => 'A new proof cannot be sent for this booking.']);
        }

        try {
            $start = Carbon::parse(
                $appointment->appointment_date->toDateString().' '.substr((string) $appointment->start_time, 0, 5)
            );
        } catch (\Throwable) {
            return back()->withErrors(['resubmit' => 'A new proof cannot be sent for this booking.']);
        }

        if (! $start->isFuture()) {
            return back()->withErrors(['resubmit' => 'This appointment is in the past — please make a fresh booking.']);
        }

        if ($this->slotTakenByOther($appointment)) {
            return back()->withErrors(['resubmit' => 'That time slot was just taken — please make a fresh booking for another time.']);
        }

        $validated = $request->validate([
            'payment_proof' => 'required|image|max:5120',
        ]);

        DB::transaction(function () use ($request, $appointment, $payment, $validated) {
            $payment->update([
                'proof_path' => $request->file('payment_proof')->store('payment_proofs', 'public'),
                'status' => PaymentStatus::Pending,
                'rejection_category' => null,
                'rejection_reason' => null,
                'refund_status' => null,
                'refund_requested_at' => null,
                'refund_sent_at' => null,
                'refund_sent_by' => null,
            ]);

            $appointment->update(['status' => AppointmentStatus::Unverified]);
        });

        return redirect()->route('account.bookings')
            ->with('success', 'New proof submitted — the team will verify it shortly.');
    }

    public function profile()
    {
        $user = Auth::user();
        $profile = $user->clientProfile;
        $defaultAddress = $user->addresses()->where('is_default', true)->first();

        // Completion meter shown on the redesigned Profile page — 5 fields
        // that actually matter for a booking to go smoothly (contact number
        // + a usable address), out of the whole Personal + Address section.
        // Purely informational, doesn't gate anything.
        $completionFields = [
            $profile?->first_name,
            $profile?->mobile_number,
            $defaultAddress?->street_address,
            $defaultAddress?->barangay,
            $defaultAddress?->city_municipality,
        ];
        $profileCompletion = (int) round(
            (count(array_filter($completionFields)) / count($completionFields)) * 100
        );

        return view('account.profile', [
            'user' => $user,
            // Both nullable — an existing Client who registered before this
            // schema existed simply has no row yet. The view renders a
            // "Complete your profile" prompt instead of crashing.
            'profile' => $profile,
            'defaultAddress' => $defaultAddress,
            'profileCompletion' => $profileCompletion,
        ]);
    }

    /**
     * One combined Save covering Account (email/password), Personal
     * (client_profiles), and Address (default client_addresses row) — see
     * the Client Profile + Address schema report for why this is one form/
     * one transaction rather than three. Personal and Address are each
     * optional as a *section* (an existing Client can update just their
     * email without being forced to complete the rest), but internally
     * consistent: if any field in a section is submitted, that whole
     * section is required together, since client_profiles.first_name/
     * last_name and client_addresses' core fields are NOT NULL.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $fields = $request->validate([
            // Account
            'email' => 'required|string|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',

            // Personal — optional as a section, but first/last name must
            // travel together (client_profiles has no room for a half name).
            'first_name' => 'nullable|required_with:last_name|string|max:100',
            'last_name' => 'nullable|required_with:first_name|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'gender' => ['nullable', new Enum(Gender::class)],
            'birthdate' => 'nullable|date|before:today',
            'mobile_number' => ['nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],

            // Address — optional as a section, core fields travel together.
            'street_address' => 'nullable|required_with:barangay,city_municipality,province|string|max:255',
            'barangay' => 'nullable|required_with:street_address|string|max:100',
            'city_municipality' => 'nullable|required_with:street_address|string|max:100',
            'province' => 'nullable|required_with:street_address|string|max:100',
            'postal_code' => 'nullable|string|max:10|regex:/^[0-9]{4,10}$/',
        ]);

        DB::transaction(function () use ($user, $fields) {
            $user->email = $fields['email'];
            if (! empty($fields['password'])) {
                $user->password = Hash::make($fields['password']);
            }

            if (! empty($fields['first_name'])) {
                // users.name stays the single, always-in-sync display value
                // every other read site (Admin, Mobile, mail) already relies
                // on — recomputed here, never left stale.
                $user->name = trim(preg_replace(
                    '/\s+/',
                    ' ',
                    "{$fields['first_name']} ".($fields['middle_name'] ?? '')." {$fields['last_name']}"
                ));
            }
            $user->save();

            if (! empty($fields['first_name'])) {
                $user->clientProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'first_name' => $fields['first_name'],
                        'middle_name' => $fields['middle_name'] ?? null,
                        'last_name' => $fields['last_name'],
                        'gender' => $fields['gender'] ?? null,
                        'birthdate' => $fields['birthdate'] ?? null,
                        'mobile_number' => $fields['mobile_number'] ?? null,
                    ]
                );
            }

            if (! empty($fields['street_address'])) {
                $address = $user->addresses()->where('is_default', true)->first();
                $addressData = [
                    'street_address' => $fields['street_address'],
                    'barangay' => $fields['barangay'],
                    'city_municipality' => $fields['city_municipality'],
                    'province' => $fields['province'],
                    'postal_code' => $fields['postal_code'] ?? null,
                ];

                if ($address) {
                    $address->update($addressData);
                } else {
                    $user->addresses()->create($addressData + ['is_default' => true]);
                }
            }
        });

        return back()->with('success', 'Profile updated.');
    }
}
