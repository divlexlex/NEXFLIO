<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BusinessHours;
use App\Models\Promo;
use App\Models\Service;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Public website chat assistant. Answers visitor questions from a live
 * business-data snapshot (services, hours, promos, FAQs, contact details,
 * payment methods, policies) and never includes any client/user row or PII.
 *
 * Nothing is persisted: the snapshot is computed per request and the
 * conversation lives only in the browser and the Gemini call.
 */
class ClientChatController extends Controller
{
    public function ask(Request $request, GeminiService $gemini)
    {
        // Validated manually so guests always receive JSON, never a redirect.
        $validator = Validator::make($request->all(), [
            'question' => ['required', 'string', 'max:500'],
            'history' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $validated = $validator->validated();

        if (! $gemini->isConfigured()) {
            return response()->json(['answer' => null, 'configured' => false]);
        }

        // Normalize to role/text, accept only user/assistant roles, and keep
        // just the last 6 turns — the service enforces the cap again too.
        $history = [];
        foreach (array_slice($validated['history'] ?? [], -6) as $turn) {
            if (! is_array($turn) || ! in_array($turn['role'] ?? null, ['user', 'assistant'], true)) {
                continue;
            }

            $text = trim((string) ($turn['text'] ?? ''));
            if ($text !== '') {
                $history[] = ['role' => $turn['role'], 'text' => $text];
            }
        }

        $answer = $gemini->answerCustomerQuestion($validated['question'], $this->knowledgeSnapshot(), $history);

        return response()->json(['answer' => $answer, 'configured' => true]);
    }

    /**
     * Business-only KNOWLEDGE snapshot. Deliberately contains no user/client
     * names, emails, phones, ids, or appointment rows.
     *
     * @return array<string, mixed>
     */
    private function knowledgeSnapshot(): array
    {
        $services = Service::query()
            ->where('status', 'active')
            ->orderBy('category')
            ->orderBy('name')
            ->get(['category', 'name', 'price', 'duration_minutes', 'down_payment'])
            ->map(fn (Service $service) => [
                'category' => $service->category,
                'name' => $service->name,
                'price' => $service->price !== null ? (float) $service->price : null,
                'duration_minutes' => (int) $service->duration_minutes,
                'down_payment' => $service->down_payment !== null ? (float) $service->down_payment : null,
            ])
            ->values()
            ->all();

        // Monday-first, read from the business_hours table — never the stale
        // config/contact_info.php 'hours' string.
        $hoursByDay = BusinessHours::all()->keyBy('day_of_week');
        $businessHours = [];
        foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
            $row = $hoursByDay->get($day);

            $businessHours[] = [
                'day' => BusinessHours::dayName($day),
                'hours' => (! $row || ! $row->is_open)
                    ? 'Closed'
                    : Carbon::parse($row->open_at)->format('g:i A').' – '.Carbon::parse($row->close_at)->format('g:i A'),
            ];
        }

        $promos = Promo::live()
            ->with('services:id,name')
            ->orderBy('title')
            ->get()
            ->map(fn (Promo $promo) => [
                'title' => $promo->title,
                'discount_label' => $promo->discountLabel(),
                'services' => $promo->services->pluck('name')->values()->all(),
            ])
            ->values()
            ->all();

        $faqs = collect(config('faqs.categories', []))
            ->map(fn (array $faq) => [
                'question' => $faq['question'],
                'answer' => $faq['answer'],
            ])
            ->values()
            ->all();

        $contact = config('contact_info', []);

        return [
            'services' => $services,
            'business_hours' => $businessHours,
            'promos' => $promos,
            'faqs' => $faqs,
            'contact' => [
                'business_name' => $contact['business_name'] ?? null,
                'address_line' => $contact['address_line'] ?? null,
                'phones' => array_values($contact['phones'] ?? []),
            ],
            'payment_methods' => array_values(config('payment.methods', [])),
            'policies' => [
                'Online booking: the earliest bookable date is tomorrow (no same-day online bookings); walk-ins are welcome subject to availability.',
                "A service's down payment (shown in KNOWLEDGE.services) secures the slot and is deducted from the total; any balance is paid at the branch at check-out.",
                "Payments are made to the branch's accounts (GCash, Maya, GoTyme, or other bank transfer) with proof uploaded for verification; cash is accepted where the booking flow offers it.",
            ],
        ];
    }
}
