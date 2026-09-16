<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Responder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Responder accounts are pre-built, one shared login per agency (PNP,
 * BFP, SARS, HCU, MSWD) — see ResponderSeeder and the admin panel's
 * "Responder Accounts" page. There is no self-service registration, so
 * login/me/logout are the only endpoints this controller needs (see
 * api.php — register/verify-email routes are intentionally not exposed
 * for responders).
 */
class ResponderAuthController extends Controller
{
    /**
     * createToken() never revokes prior tokens, so any number of staff
     * within the same agency can be signed in on their own devices at
     * once — that's the point of one shared account per agency.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $responder = Responder::where('email', $request->email)->first();

        if (! $responder || ! Hash::check($request->password, $responder->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if ($responder->status !== 'active') {
            return response()->json(['message' => 'This account has been deactivated. Contact your MDRRMO admin.'], 403);
        }

        $token = $responder->createToken('responder-app')->plainTextToken;

        return response()->json([
            'message'   => 'Logged in.',
            'token'     => $token,
            'responder' => $responder,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}