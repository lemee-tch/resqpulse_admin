<?php

namespace App\Services;

use App\Models\Citizen;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;

class PushNotificationService
{
    public function broadcastToAllCitizens(string $title, string $body): void
    {
        $tokens = Citizen::whereNotNull('fcm_token')->pluck('fcm_token')->toArray();

        Log::info('FCM broadcast attempt', [
            'title' => $title,
            'token_count' => count($tokens),
        ]);

        if (empty($tokens)) {
            Log::warning('FCM broadcast skipped: no citizen fcm_tokens found in database.');
            return;
        }

        $messaging = Firebase::messaging();

        // Send in chunks of 500 (FCM's multicast limit)
        foreach (array_chunk($tokens, 500) as $chunk) {
            $message = CloudMessage::new()->withNotification(
                Notification::create($title, $body)
            );

            try {
                $report = $messaging->sendMulticast($message, $chunk);

                Log::info('FCM broadcast result', [
                    'success_count' => $report->successes()->count(),
                    'failure_count' => $report->failures()->count(),
                ]);

                foreach ($report->failures()->getItems() as $failure) {
                    Log::error('FCM send failure', [
                        'target' => $failure->target()->value(),
                        'error'  => $failure->error()->getMessage(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('FCM broadcast exception', [
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}