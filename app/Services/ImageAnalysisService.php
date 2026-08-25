<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageAnalysisService
{
    protected const EMERGENCY_TYPES = [
        'Fire', 'Flood', 'Earthquake', 'Accident', 'Medical Emergency', 'Landslide', 'Other',
    ];

    /**
     * Baseline priority per emergency type, mirroring the urgency already
     * implied by PushNotificationService::AGENCY_MAP (life-threat types
     * route to faster-response agencies). Confidence adjusts this down
     * one notch when the AI wasn't sure — see priorityFor().
     */
    protected const TYPE_PRIORITY = [
        'Fire'              => 'critical',
        'Earthquake'        => 'critical',
        'Medical Emergency' => 'critical',
        'Accident'          => 'high',
        'Flood'             => 'high',
        'Landslide'         => 'high',
        'Other'             => 'moderate',
    ];

    /**
     * Classifies an incident photo already saved on the 'public' disk.
     * Returns null on any failure — this is best-effort and must never
     * block an incident/SOS from being saved.
     *
     * @return array{type: string, confidence: string, notes: string, priority: string}|null
     */
    public function classify(string $storagePath): ?array
    {
        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            Log::warning('Image analysis skipped: GEMINI_API_KEY not configured.');
            return null;
        }

        try {
            $absolutePath = Storage::disk('public')->path($storagePath);
            if (! file_exists($absolutePath)) {
                return null;
            }

            $mime = mime_content_type($absolutePath) ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($absolutePath));
            $typesList = implode(', ', self::EMERGENCY_TYPES);
            // Pinned to a stable, current model rather than a "-latest" alias —
            // aliases can silently repoint to newer/more experimental backends
            // with less predictable latency. Update this if Google deprecates
            // it again (check the Gemini API changelog for the current
            // recommended Flash-tier model).
            $model = config('services.gemini.model', 'gemini-3.5-flash');

            $prompt = "You are triage software for a municipal disaster response system. "
                . "Look at this photo, submitted with an emergency report. "
                . "Classify it into exactly one of these categories: {$typesList}. "
                . 'Respond with ONLY raw JSON in this exact shape: '
                . '{"type": "<one of the categories above>", "confidence": "high|medium|low", "notes": "<one short sentence describing what is visible, for a responder who has not seen the photo>"}';

            // Retries transient failures with a short backoff: both
            // overload/rate-limit responses (503, 429) and connection-level
            // failures (timeouts, brief network hiccups) — the latter throw
            // a ConnectionException before a response object even exists, so
            // they're caught here explicitly rather than relying on a
            // response status check. A couple of quick retries meaningfully
            // raises the success rate for a best-effort feature like this,
            // with no real downside since we still fall through to null
            // (and the incident still saves fine) if all attempts fail.
            $attempts = 0;
            $maxAttempts = 3;
            $response = null;

            do {
                $attempts++;

                try {
                    $response = Http::withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'Content-Type'   => 'application/json',
                    ])->timeout(30)->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                        [
                            'contents' => [[
                                'parts' => [
                                    ['text' => $prompt],
                                    [
                                        'inline_data' => [
                                            'mime_type' => $mime,
                                            'data'      => $base64,
                                        ],
                                    ],
                                ],
                            ]],
                            'generationConfig' => [
                                'responseMimeType' => 'application/json',
                                'maxOutputTokens'   => 200,
                                // Gemini 3.x models "think" before responding
                                // by default (medium effort); this is a
                                // single-image classification, not a task
                                // needing deep reasoning, so we force the
                                // lightest thinking pass to keep it fast.
                                'thinkingConfig' => [
                                    'thinkingLevel' => 'low',
                                ],
                            ],
                        ]
                    );
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::warning('Image analysis connection attempt failed', [
                        'attempt' => $attempts,
                        'error'   => $e->getMessage(),
                    ]);
                    $response = null;
                }

                $isRetryable = $response === null
                    || (! $response->successful() && in_array($response->status(), [429, 503], true));

                if ($isRetryable && $attempts < $maxAttempts) {
                    usleep(1_500_000); // 1.5s backoff before the next attempt
                }
            } while ($isRetryable && $attempts < $maxAttempts);

            if ($response === null) {
                Log::warning('Image analysis failed after retries: connection error (timeout or network issue) on every attempt.');
                return null;
            }

            if (! $response->successful()) {
                Log::warning('Image analysis API call failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            if (! $text) {
                return null;
            }

            $parsed = json_decode($text, true);

            if (! is_array($parsed) || empty($parsed['type'])) {
                return null;
            }

            // Guard against the model returning a category outside our list.
            // Matched case-insensitively (and trimmed) since Gemini sometimes
            // returns e.g. "fire" or " Fire " despite the prompt asking for
            // an exact match — without this, those get silently bucketed
            // into "Other" even though the detection was correct.
            $matchedType = collect(self::EMERGENCY_TYPES)
                ->first(fn ($type) => strcasecmp(trim((string) $parsed['type']), $type) === 0);

            $parsed['type'] = $matchedType ?? 'Other';
            $parsed['confidence'] = $parsed['confidence'] ?? 'low';

            return [
                'type'       => $parsed['type'],
                'confidence' => $parsed['confidence'],
                'notes'      => $parsed['notes'] ?? '',
                'priority'   => self::priorityFor($parsed['type'], $parsed['confidence']),
            ];
        } catch (\Throwable $e) {
            Log::warning('Image analysis threw an exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Suggests an incident priority from the AI-detected type and its
     * confidence. A low-confidence guess is stepped down one level —
     * an uncertain "Fire" detection shouldn't page critical the same
     * way a high-confidence one does.
     */
    public static function priorityFor(string $type, string $confidence): string
    {
        $base = self::TYPE_PRIORITY[$type] ?? 'moderate';

        if (strtolower($confidence) !== 'low') {
            return $base;
        }

        return match ($base) {
            'critical' => 'high',
            'high'     => 'moderate',
            default    => 'low',
        };
    }
}