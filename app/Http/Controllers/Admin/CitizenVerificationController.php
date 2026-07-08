<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CitizenRejected;
use App\Models\Citizen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CitizenVerificationController extends Controller
{
    public function index()
    {
        $citizens = Citizen::orderByRaw("verification_status = 'pending' DESC")
            ->orderByDesc('created_at')
            ->get();

        return view('citizen-verification', compact('citizens'));
    }

    public function approve(Citizen $citizen)
    {
        $citizen->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        return back()->with('success', "{$citizen->full_name} has been verified.");
    }

    public function reject(Request $request, Citizen $citizen)
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        $name = $citizen->full_name;
        $email = $citizen->email;
        $reason = $request->rejection_reason;

        // Revoke any active login tokens before deleting, so any session
        // currently logged in on the phone gets immediately invalidated.
        $citizen->tokens()->delete();

        // Send the notification BEFORE deleting — once gone, there's no
        // model left to pull the name/email from.
        Mail::to($email)->send(new CitizenRejected($name, $reason));

        $citizen->delete();

        return back()->with('success', "{$name}'s account was rejected and removed. A notification was sent to {$email}.");
    }
}