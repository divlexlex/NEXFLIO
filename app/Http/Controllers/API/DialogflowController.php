<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

/**
 * Dialogflow ES fulfillment webhook. Authenticated by a shared token header
 * configured in the Dialogflow console (DIALOGFLOW_WEBHOOK_TOKEN).
 */
class DialogflowController extends Controller
{
    public function webhook(Request $request)
    {
        $expected = config('services.dialogflow.webhook_token');

        if (empty($expected) || ! hash_equals($expected, (string) $request->header('X-Webhook-Token'))) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $intent = $request->input('queryResult.intent.displayName', '');
        $parameters = $request->input('queryResult.parameters', []);

        $text = match ($intent) {
            'services.prices' => $this->servicesPrices($parameters['category'] ?? null),
            'hours.location' => 'We are open daily from 10:00 AM to 8:00 PM. '
                . 'Walk-ins are welcome, but booked appointments are prioritized.',
            'booking.how' => 'Bookings are made through the Perfect Nails mobile app: '
                . 'pick a service, choose your specialist and time slot, then upload '
                . 'your proof of payment. Your booking is confirmed once our manager '
                . 'verifies it — you will get a notification.',
            default => 'You can ask me about our services and prices, opening hours, '
                . 'or how to book an appointment.',
        };

        return response()->json(['fulfillmentText' => $text]);
    }

    private function servicesPrices(?string $category): string
    {
        $services = Service::where('status', 'active')
            ->when($category, fn ($query) => $query->where('category', 'like', "%{$category}%"))
            ->orderBy('category')
            ->orderBy('name')
            ->limit(12)
            ->get();

        if ($services->isEmpty()) {
            return $category
                ? "We don't currently have active services under \"{$category}\". "
                    . 'Ask me about Nails, Massage, or Aesthetics!'
                : 'Our service menu is being updated — please check the app for the latest offerings.';
        }

        $lines = $services
            ->map(fn (Service $service) => "{$service->name} — ₱" . number_format((float) $service->price, 2)
                . " ({$service->duration_minutes} mins)")
            ->implode('; ');

        return "Here are our services: {$lines}. You can book any of these on the Perfect Nails app!";
    }
}
