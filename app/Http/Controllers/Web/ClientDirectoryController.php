<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin Clients directory (Owner / Manager) — searchable list of registered
 * clients, per-client profile with stats and full appointment archive, and
 * staff-written preference notes stored on the client profile.
 */
class ClientDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $clients = User::where('role_id', User::ROLE_CLIENT)
            ->with('clientProfile')
            ->when($q !== '', fn ($query) => $query->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhereHas('clientProfile', fn ($query) => $query->where('mobile_number', 'like', "%{$q}%"));
            }))
            ->withCount('appointments as bookings_count')
            ->withMax('appointments as last_appointment_date', 'appointment_date')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.clients.index', [
            'clients' => $clients,
            'q' => $q,
        ]);
    }

    public function show(User $user)
    {
        abort_unless($user->role_id === User::ROLE_CLIENT, 404);

        $user->loadMissing('clientProfile');

        $appointmentIds = Appointment::where('user_id', $user->id)->pluck('id');

        $totalPaid = (float) Payment::whereIn('appointment_id', $appointmentIds)
            ->where('status', PaymentStatus::Verified)
            ->sum('amount');

        $history = Appointment::with(['service:id,name', 'personnel:id,name'])
            ->where('user_id', $user->id)
            ->orderByDesc('appointment_date')
            ->orderByDesc('start_time')
            ->paginate(10);

        return view('admin.clients.show', [
            'client' => $user,
            'profile' => $user->clientProfile,
            'totalBookings' => $appointmentIds->count(),
            'completedCount' => Appointment::where('user_id', $user->id)
                ->where('status', AppointmentStatus::Completed)
                ->count(),
            'cancelledCount' => Appointment::where('user_id', $user->id)
                ->whereIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow])
                ->count(),
            'totalPaid' => $totalPaid,
            'history' => $history,
        ]);
    }

    public function updateNotes(Request $request, User $user)
    {
        abort_unless($user->role_id === User::ROLE_CLIENT, 404);

        $validated = $request->validate([
            'preferences_notes' => 'nullable|string|max:2000',
        ]);

        $parts = preg_split('/\s+/', trim($user->name ?? ''), 2);

        $profile = $user->clientProfile()->firstOrCreate(
            ['user_id' => $user->id],
            ['first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '']
        );
        $profile->update(['preferences_notes' => $validated['preferences_notes']]);

        return redirect()->route('admin.clients.show', $user)->with('success', 'Preference notes saved.');
    }
}
