<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    /**
     * Toggle break status — while on break the staff member disappears from
     * the client booking pool. Accepts an optional explicit `on_break` bool;
     * without it, the current state is flipped.
     */
    public function toggleBreak(Request $request)
    {
        $profile = $request->user()->staffProfile;

        if (! $profile) {
            return response()->json(['message' => 'No staff profile found for this account.'], 422);
        }

        $target = $request->has('on_break')
            ? $request->boolean('on_break')
            : ! $profile->is_on_break;

        $profile->update([
            'is_on_break' => $target,
            'break_started_at' => $target ? now() : null,
        ]);

        return response()->json([
            'message' => $target ? 'You are now on break.' : 'You are back in the booking pool.',
            'is_on_break' => $target,
        ]);
    }
}
