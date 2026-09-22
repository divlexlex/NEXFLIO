<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\User;

/**
 * Shared by AuthController and EmailVerificationController — both are ways a
 * Staff/Client/Manager/Owner can end up fully signed in, and both must land
 * the same place afterward.
 */
trait RedirectsAfterAuth
{
    /**
     * Super Admin/Manager land on the Management dashboard, Staff on their
     * own self-service portal. A Client returns to wherever they started
     * (e.g. the public page they clicked "Book an Appointment" from) if
     * there was one, otherwise their Website account dashboard.
     */
    private function destinationFor(User $user): string
    {
        $intended = session()->pull('url.intended');

        if ($user->isManagerOrAbove()) {
            return route('admin.dashboard');
        }

        if ($user->isStaff()) {
            return route('staff.dashboard');
        }

        return $intended ?? route('account.dashboard');
    }
}
