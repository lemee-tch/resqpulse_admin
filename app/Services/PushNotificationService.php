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
     * Notifies a single citizen — used when their own report/SOS is
     * approved out of Pending Review, so they get told without waiting on
     * the "My Reports" screen to next poll. Silently does nothing if they
     * have no fcm_token registered (e.g. never granted notification
     * permission), same best-effort spirit as sendToTokens() below.
     */
    public function notifyCitizen(Citizen $citizen, string $title, string $body): void
    {
        if (! $citizen->fcm_token) {
            Log::info('FCM single-citizen send skipped: no fcm_token', ['citizen_id' => $citizen->id]);
            return;
        }

        Log::info('FCM single-citizen send attempt', [
            'citizen_id' => $citizen->id,
            'title' => $title,
        ]);

        $this->sendToTokens([$citizen->fcm_token], $title, $body, "citizen #{$citizen->id}");
    }

    /**
     * Notifies the responders on one incident — e.g. when MDRRMO sends
     * their Final report back for changes, or confirms it as resolved.
     * Best-effort like the rest: never throws back to the caller, and
     * responders with no fcm_token (or a deactivated account) are skipped.
     *
     * @param  iterable<Responder>  $responders
     */
    public function notifyResponders(iterable $responders, string $title, string $body): void
    {
        try {
            $tokens = collect($responders)
                ->filter(fn ($r) => $r instanceof Responder && $r->status === 'active' && $r->fcm_token)
                ->pluck('fcm_token')
                ->unique()
                ->values()
                ->all();

            Log::info('FCM incident-team send attempt', [
                'title' => $title,
                'token_count' => count($tokens),
            ]);

            $this->sendToTokens($tokens, $title, $body, 'incident team');
        } catch (\Throwable $e) {
            Log::error('FCM incident-team send failed', ['error' => $e->getMessage()]);
        }
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
     * Sends the incident to the responders — called when the admin
     * verifies it (Admin\IncidentController::approve()), and again with
     * $onlyAgencies when the admin adds a team later (updateAssignment()),
     * so only the newly added team is notified.
     *
     * Goes to the teams the admin assigned (assigned_teams). Incidents
     * approved before teams were assigned fall back to the usual routing
     * by type (AGENCY_MAP). Best-effort — this never throws back to the
     * caller, since a notification failure should never undo a saved
     * report.
     */
    public function dispatchToRespondersForIncident(Incident $incident, ?array $onlyAgencies = null): void
    {
        try {
            if ($onlyAgencies !== null) {
                $agencies = $onlyAgencies;
            } elseif (is_array($incident->assigned_teams) && $incident->assigned_teams !== []) {
                $agencies = $incident->assigned_teams;
            } else {
                // AI-detected type (only ever set on SOS reports with a
                // classified photo) takes priority over the raw
                // emergency_type, so a photo that looks like a fire also
                // notifies BFP even though the type on the record is
                // literally "SOS Emergency". Falls back to the plain
                // emergency_type for regular (non-SOS) reports.
                $routingType = $incident->ai_detected_type ?? $incident->emergency_type;
                $agencies = self::agenciesFor($routingType);
            }

            $isSos = $incident->emergency_type === 'SOS Emergency';
            $title = $isSos ? '🆘 SOS Emergency Alert' : "🚨 New {$incident->emergency_type} Report";

            // The priority the admin picked when approving, e.g.
            // "🚨 New Fire Report — High priority".
            if (! $isSos && $incident->priority) {
                $title .= ' — ' . ucfirst($incident->priority) . ' priority';
            }
            if ($onlyAgencies !== null) {
                $title = ($isSos ? '🆘 SOS' : "🚨 {$incident->emergency_type}") . ' — your team was added';
            }

            $bodyParts = [$incident->location];
            if ($incident->description) {
                $bodyParts[] = Str::limit($incident->description, 80);
            }
            // The admin's instructions for the team, if any.
            if (filled($incident->assignment_notes)) {
                $bodyParts[] = 'MDRRMO: ' . Str::limit($incident->assignment_notes, 80);
            }
            $body = implode(' — ', $bodyParts);

            foreach (array_unique($agencies) as $agency) {
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
            // back into the admin's approve/assign request. The incident
            // is already saved by the time this runs.
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
}