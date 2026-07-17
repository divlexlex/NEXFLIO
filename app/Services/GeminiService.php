<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin Gemini API client for the dashboard's AI insight card. Receives only
 * aggregated metrics (totals, counts, percentages) — never client PII.
 * Every failure path returns null so the dashboard degrades gracefully.
 */
class GeminiService
{
    public function isConfigured(): bool
    {
        return ! empty(config('services.gemini.key'));
    }

    /**
     * @param array<string, mixed> $metrics aggregated numbers only
     */
    public function summarizeOperations(array $metrics): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $model = config('services.gemini.model');

        $prompt = "You are a business analyst for a small spa "
            . "(nails, massage, aesthetics). Based on the operational metrics "
            . "below, write a short insight summary for the owner: 3-4 plain "
            . "sentences covering how the business is doing, one notable trend, "
            . "and one concrete recommendation. No markdown, no preamble.\n\n"
            . json_encode($metrics, JSON_PRETTY_PRINT);

        try {
            $response = Http::timeout(15)
                ->withHeader('x-goog-api-key', config('services.gemini.key'))
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                    [
                        'contents' => [
                            ['parts' => [['text' => $prompt]]],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.4,
                            'maxOutputTokens' => 512,
                        ],
                    ]
                );

            if (! $response->successful()) {
                Log::warning('Gemini insight request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            return is_string($text) && trim($text) !== '' ? trim($text) : null;
        } catch (\Throwable $e) {
            Log::warning('Gemini insight request threw', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
