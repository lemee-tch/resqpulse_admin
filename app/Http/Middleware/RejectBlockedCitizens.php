<?php

namespace App\Http\Middleware;

use App\Models\Citizen;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends a blocked resident's session (item 54). Every signed-in API call
 * from a resident MDRRMO blocked gets 401 with the reason, and the login
 * on that phone is removed — the residents app then goes back to guest
 * mode and shows the message. Responders aren't affected.
 *
 * Used on the auth:sanctum routes in routes/api.php. Reports and SOS
 * (Api\IncidentController::store() / sos()) check it themselves, since
 * they also take guests.
 */
class RejectBlockedCitizens
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof Citizen && $user->isBlocked()) {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json([
                'message' => $user->blockedMessage(),
                'blocked' => true,
            ], 401);
        }

        return $next($request);
    }
}
