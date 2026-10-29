<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\Incident;
use App\Models\Responder;
use App\Models\ResponderPushToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;

/**
 * Pushes to the residents and responders apps.
 *
 * Responder pushes say what they're about in their data, so the app knows
 * which ones ring (IncidentAlertService in the responder app):
 *
 *   type=incident  a report or SOS sent to the team — rings, with
 *                  incident_id (and kind: new / again / help)
 *   type=report    MDRRMO approved or sent back the team's report
 *   type=alert     a disaster alert from Alerts & Broadcast
 *
 * Incident pushes also go on the app's ringing Android channel
 * (INCIDENT_CHANNEL), so they ring even when the app is closed.
 */
class PushNotificationService
{
    /** The responder app's ringing notification channel — see IncidentAlertService. */
    public const INCIDENT_CHANNEL = 'incident_alerts_v2';

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
     * their report back for changes, or approves it as resolved.
     * Best-effort like the rest: never throws back to the caller, and
     * responders with no phone signed in (or a deactivated account) are
     * skipped. Every phone signed in to their accounts gets it.
     *
     * @param  iterable<Responder>  $responders
     */
    public function notifyResponders(iterable $responders, string $title, string $body, ?Incident $incident = null): void
    {
        try {
            $tokens = $this->tokensOf(
                collect($responders)->filter(fn ($r) => $r instanceof Responder && $r->status === 'active')
            );

            Log::info('FCM incident-team send attempt', [
                'title' => $title,
                'token_count' => count($tokens),
            ]);

            $this->sendToTokens($tokens, $title, $body, 'incident team', array_filter([
                'type'        => 'report',
                'incident_id' => $incident ? (string) $incident->id : null,
            ]));
        } catch (\Throwable $e) {
            Log::error('FCM incident-team send failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Notifies every phone signed in to an active responder account of an
     * agency (PNP/BFP/SARS/HCU). $data and $channel: see the class comment.
     */
    public function broadcastToAgency(string $agency, string $title, string $body, array $data = [], ?string $channel = null): void
    {
        $tokens = $this->tokensOf(Responder::where('agency', $agency)->where('status', 'active')->with('pushTokens')->get());

        Log::info('FCM agency dispatch attempt', [
            'agency' => $agency,
            'title' => $title,
            'token_count' => count($tokens),
        ]);

        $this->sendToTokens($tokens, $title, $body, "agency dispatch ({$agency})", $data, $channel);
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
        $tokens = $this->tokensOf(Responder::where('status', 'active')->with('pushTokens')->get());

        Log::info('FCM broadcast attempt', [
            'title' => $title,
            'token_count' => count($tokens),
        ]);

        $this->sendToTokens($tokens, $title, $body, 'responder broadcast', ['type' => 'alert']);
    }

    /**
     * Every phone signed in to these responder accounts.
     *
     * @param  iterable<Responder>  $responders
     * @return list<string>
     */
    private function tokensOf(iterable $responders): array
    {
        return collect($responders)
            ->flatMap(fn (Responder $responder) => $responder->pushTargets())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Sends the incident to the responders — called when the admin approves
     * it (Admin\IncidentController::approve(): the team(s) the admin
     * chose), and
     * with $onlyAgencies when the admin notifies specific teams later
     * (updateAssignment()). $kind picks the title:
     *
     *   new    "🚨 New Fire Report — High priority" (approving)
     *   again  "🚨 Fire report — no one has accepted yet"
     *   help   "🚨 Fire — MDRRMO needs your team" (someone is on it; the
     *          team is asked to help)
     *
     * Goes to the teams the admin assigned (assigned_teams). Incidents
     * approved before teams were assigned fall back to the usual routing
     * by type (AGENCY_MAP). Best-effort — this never throws back to the
     * caller, since a notification failure should never undo a saved
     * report.
     */
    public function dispatchToRespondersForIncident(Incident $incident, ?array $onlyAgencies = null, string $kind = 'new'): void
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
            if ($kind === 'again') {
                $title = ($isSos ? '🆘 SOS' : "🚨 {$incident->emergency_type} report") . ' — no one has accepted yet';
            } elseif ($kind === 'help') {
                $title = ($isSos ? '🆘 SOS' : "🚨 {$incident->emergency_type}") . ' — MDRRMO needs your team';
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

            // Tells the app it's an incident (it rings) and which one.
            $data = ['type' => 'incident', 'kind' => $kind, 'incident_id' => (string) $incident->id];

            foreach (array_unique($agencies) as $agency) {
                // Isolated per agency — one agency's dispatch failing
                // (for whatever reason) should never stop the others
                // from being notified.
                try {
                    $this->broadcastToAgency($agency, $title, $body, $data, self::INCIDENT_CHANNEL);
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
     *
     * $data travels with the push (see the class comment); $channel puts it
     * on that Android notification channel, at high priority. Tokens
     * Firebase no longer knows (the app was uninstalled, or the phone got a
     * new token) are forgotten, so they aren't tried again.
     */
    protected function sendToTokens(array $tokens, string $title, string $body, string $context, array $data = [], ?string $channel = null): void
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
            try {
                $message = CloudMessage::new()->withNotification(
                    Notification::create($title, $body)
                );
                if ($data !== []) {
                    $message = $message->withData($data);
                }
                if ($channel !== null) {
                    $message = $message->withAndroidConfig(AndroidConfig::fromArray([
                        'priority'     => 'high',
                        'notification' => [
                            'channel_id'            => $channel,
                            'sound'                 => 'alarm_sound',
                            'notification_priority' => 'PRIORITY_MAX',
                            'visibility'            => 'PUBLIC',
                        ],
                    ]));
                }

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

                $this->forgetTokens(array_merge($report->unknownTokens(), $report->invalidTokens()));
            } catch (\Throwable $e) {
                Log::error("FCM send exception ({$context})", [
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Forgets push tokens Firebase no longer accepts, so they aren't tried
     * again on every push.
     *
     * @param  list<string>  $tokens
     */
    protected function forgetTokens(array $tokens): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if ($tokens === []) {
            return;
        }

        try {
            ResponderPushToken::whereIn('token', $tokens)->delete();
            Responder::whereIn('fcm_token', $tokens)->update(['fcm_token' => null]);
            Citizen::whereIn('fcm_token', $tokens)->update(['fcm_token' => null]);
            Log::info('FCM tokens forgotten', ['count' => count($tokens)]);
        } catch (\Throwable $e) {
            Log::error('FCM tokens could not be forgotten', ['error' => $e->getMessage()]);
        }
    }
}
