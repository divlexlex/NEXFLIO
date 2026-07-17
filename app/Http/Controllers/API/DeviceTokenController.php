<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceTokenController extends Controller
{
    /** Register (or re-claim) an FCM device token after login. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:255',
            'platform' => ['sometimes', Rule::in(['android', 'ios'])],
        ]);

        // A device token belongs to whoever is signed in on that device now.
        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'android',
                'last_used_at' => now(),
            ]
        );

        return response()->json(['message' => 'Device registered.'], 201);
    }

    /** Unregister on logout so the next owner of the session gets no pushes. */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:255',
        ]);

        DeviceToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Device unregistered.']);
    }
}
