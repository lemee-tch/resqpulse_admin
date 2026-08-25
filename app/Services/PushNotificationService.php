<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\Incident;
use App\Models\Responder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;

class PushNotificationService
{
    /**
     * Which responder agency (or agencies) gets notified for each emergency
     * type. SARS is the general-purpose responder agency (Search and
     * Rescue) and acts as the fallback for anything not explicitly mapped —
     * MDRRMO is the admin/dispatcher side (the Laravel admin panel), not a
     * field agency that registers responders or receives pushes.
     * Adjust freely — this is the single place that controls routing.
     */
    protected const AGENCY_MAP = [
        'Fire'              => ['BFP', 'SARS'],
        'Accident'          => ['PNP', 'SARS'],
        'Flood'             => ['SARS', 'HCU'],
        'Earthquake'        => ['SARS', 'HCU'],
        'Landslide'         => ['SARS', 'HCU'],
        'Medical Emergency' => ['SARS', 'HCU'],
        'Other'             => ['SARS'],
    ];

    /**
     * Used when the type isn't in AGENCY_MAP at all — e.g. a plain
     * 'SOS Emergency' with no photo, so no AI-detected type to route by.
     */
    protected const DEFAULT_AGENCIES = ['SARS'];

    /**
     * Given a routing type (an ai_detected_type or emergency_type value),
     * returns which agency/agencies should see it. Public + static so it
     * can be reused both for push dispatch (below) and for filtering
     * which incidents show up in a responder's "Assigned Incidents" feed.
     */
    public static function agenciesFor(?string $routingType): array
    {
        return self::AGENCY_MAP[$routingType] ?? self::DEFAULT_AGENCIES;
    }

    public function broadcastToAllCitizens(string $title, string $body): void
    {
        $tokens = Citizen::whereNotNull('fcm_token')->pluck('fcm_token')->toArray();

        Log::info('FCM broadcast attempt', [
            'title' => $title,
            'token_count' => count($tokens),
        ]);

        $this->sendToTokens($tokens, $title, $body, 'citizen broadcast');
    }

    /**
     * Notifies every active responder in a given agency (PNP/BFP/SARS)
     * who has an FCM token registered.
     */
    public function broadcastToAgency(string $agency, string $title, string $body): void
    {
        $tokens = Responder::where('agency', $agency)
            ->where('status', 'active')
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();

        Log::info('FCM agency dispatch attempt', [
            'agency' => $agency,
            'title' => $title,
            'token_count' => count($tokens),
        ]);

        $this->sendToTokens($tokens, $title, $body, "agency dispatch ({$agency})");
    }

    /**
     * Notifies every active responder regardless of agency — used by the
     * Alerts & Broadcast admin panel when "Send To" is Responders or
     * Both. Unlike broadcastToAgency(), this doesn't route by emergency
     * type; it's a blanket announcement (e.g. a general advisory), not an
     * incident dispatch.
     */
    public function broadcastToAllResponders(string $title, string $body): void
    {
        $tokens = Responder::where('status', 'active')
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();

        Log::info('FCM broadcast attempt', [
            'title' => $title,
            'token_count' => count($tokens),
        ]);

        $this->sendToTokens($tokens, $title, $body, 'responder broadcast');
    }

    /**
     * Entry point called right after an incident/SOS is created. Figures
     * out which agency (or agencies) should be notified and sends the
     * push. Best-effort — this never throws back to the caller, since a
     * notification failure should never undo a saved report.
     */
    public function dispatchToRespondersForIncident(Incident $incident): void
    {
        try {
            // AI-detected type (only ever set on SOS reports with a
            // classified photo) takes priority over the raw
            // emergency_type, so a photo that looks like a fire also
            // notifies BFP even though the type on the record is
            // literally "SOS Emergency". Falls back to the plain
            // emergency_type for regular (non-SOS) reports.
            $routingType = $incident->ai_detected_type ?? $incident->emergency_type;

            $agencies = self::agenciesFor($routingType);

            $isSos = $incident->emergency_type === 'SOS Emergency';
            $title = $isSos ? '🆘 SOS Emergency Alert' : "🚨 New {$incident->emergency_type} Report";

            $bodyParts = [$incident->location];
            if ($incident->description) {
                $bodyParts[] = Str::limit($incident->description, 80);
            }
            $body = implode(' — ', $bodyParts);

            foreach ($agencies as $agency) {
                // Isolated per agency — one agency's dispatch failing
                // (for whatever reason) should never stop the others
                // from being notified.
                try {
                    $this->broadcastToAgency($agency, $title, $body);
                } catch (\Throwable $e) {
                    Log::error("Responder dispatch failed for agency ({$agency})", [
                        'incident_id' => $incident->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Final safety net — this method must never be able to throw
            // back into IncidentController::store()/sos(). The incident
            // is already saved by the time this runs; nothing here should
            // ever be able to turn a successful report into a failed one.
            Log::error('Responder dispatch failed unexpectedly', [
                'incident_id' => $incident->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Shared multicast-send logic used by both citizen broadcasts and
     * agency dispatches — chunks tokens into FCM's 500-per-call limit and
     * logs successes/failures per chunk.
     */
    private function sendToTokens(array $tokens, string $title, string $body, string $context): void
    {
        if (empty($tokens)) {
            Log::warning("FCM send skipped: no fcm_tokens found for {$context}.");
            return;
        }

        // Resolving Firebase::messaging() itself can throw (e.g. a missing
        // or misconfigured service account file) — this must never be
        // allowed to bubble up into the citizen-facing incident/SOS
        // request that triggered it. The incident is already saved by
        // this point; a push-notification failure should only ever mean
        // "no one got pushed," never "the report failed to submit."
        try {
            $messaging = Firebase::messaging();
        } catch (\Throwable $e) {
            Log::error("FCM send skipped: Firebase could not be initialized for {$context}.", [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        foreach (array_chunk($tokens, 500) as $chunk) {
            $message = CloudMessage::new()->withNotification(
                Notification::create($title, $body)
            );

            try {
                $report = $messaging->sendMulticast($message, $chunk);

                Log::info("FCM send result ({$context})", [
                    'success_count' => $report->successes()->count(),
                    'failure_count' => $report->failures()->count(),
                ]);

                foreach ($report->failures()->getItems() as $failure) {
                    Log::error("FCM send failure ({$context})", [
                        'target' => $failure->target()->value(),
                        'error'  => $failure->error()->getMessage(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error("FCM send exception ({$context})", [
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
        /**
     * Suggests an incident priority from the AI-detected type and its
     * confidence. Low-confidence detections are stepped down one level —
     * an uncertain "Fire" guess shouldn't automatically page critical
     * the same way a high-confidence one does.
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