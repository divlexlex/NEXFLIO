<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Firebase Cloud Messaging (HTTP v1) client — self-contained: signs
 * the service-account JWT with openssl instead of pulling in an SDK.
 * Silently no-ops when FIREBASE_CREDENTIALS is not configured.
 */
class FcmService
{
    public function isConfigured(): bool
    {
        $path = config('services.fcm.credentials');

        return ! empty($path) && is_file($path);
    }

    /** Send a notification to every registered device of the user. */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        foreach (DeviceToken::where('user_id', $user->id)->get() as $device) {
            $this->sendToToken($device, $title, $body, $data);
        }
    }

    private function sendToToken(DeviceToken $device, string $title, string $body, array $data): void
    {
        $accessToken = $this->accessToken();

        if ($accessToken === null) {
            return;
        }

        $projectId = config('services.fcm.project_id') ?: $this->credentials()['project_id'] ?? null;

        if ($projectId === null) {
            return;
        }

        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $device->token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => array_map('strval', $data),
                    ],
                ]);

            if ($response->successful()) {
                $device->update(['last_used_at' => now()]);

                return;
            }

            // Dead-token cleanup: FCM reports unregistered/invalid tokens.
            $error = $response->json('error.status');
            if (in_array($error, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                $device->delete();

                return;
            }

            Log::warning('FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Throwable $e) {
            Log::warning('FCM send threw', ['error' => $e->getMessage()]);
        }
    }

    /** OAuth2 access token from the service account, cached until expiry. */
    private function accessToken(): ?string
    {
        return Cache::remember('fcm:access_token', now()->addMinutes(50), function () {
            $credentials = $this->credentials();

            if ($credentials === null) {
                return null;
            }

            $now = time();
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signature = '';
            if (! openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], 'sha256WithRSAEncryption')) {
                Log::warning('FCM: failed to sign service-account JWT');

                return null;
            }

            $jwt = "{$header}.{$claims}." . $this->base64UrlEncode($signature);

            try {
                $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                return $response->successful() ? $response->json('access_token') : null;
            } catch (\Throwable $e) {
                Log::warning('FCM token exchange threw', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    private function credentials(): ?array
    {
        $path = config('services.fcm.credentials');

        if (empty($path) || ! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
