<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Single dispatch point for client/staff notifications: writes the in-app
 * row, queues the email (when one applies), and queues the FCM push. Email
 * and push degrade silently when their providers are not configured.
 */
class NotificationService
{
    public function notify(
        User $user,
        string $title,
        string $body,
        ?Mailable $mailable = null,
        array $pushData = [],
    ): void {
        Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
        ]);

        if ($mailable !== null && ! empty($user->email)) {
            Mail::to($user->email)->queue($mailable);
        }

        SendPushNotification::dispatch($user->id, $title, $body, $pushData);
    }
}
