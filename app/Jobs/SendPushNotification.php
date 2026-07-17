<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {
    }

    public function handle(FcmService $fcm): void
    {
        $user = User::find($this->userId);

        if ($user !== null) {
            $fcm->sendToUser($user, $this->title, $this->body, $this->data);
        }
    }
}
