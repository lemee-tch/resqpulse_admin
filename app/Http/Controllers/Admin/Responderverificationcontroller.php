<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ResponderRejected;
use App\Models\Responder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ResponderVerificationController extends Controller
{
    public function index()
    {
        $responders = Responder::orderByDesc('created_at')->get();

        return view('responder-verification', compact('responders'));
    }

    public function approve(Responder $responder)
    {
        $responder->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        return back()->with('success', "{$responder->full_name} has been verified.");
    }

    public function reject(Request $request, Responder $responder)
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        $name = $responder->full_name;
        $email = $responder->email;
        $reason = $request->rejection_reason;

        // Revoke any active login tokens before deleting, so any session
        // currently logged in on the phone gets immediately invalidated.
        $responder->tokens()->delete();

        // Send the notification BEFORE deleting — once gone, there's no
        // model left to pull the name/email from.
        Mail::to($email)->send(new ResponderRejected($name, $reason));

        if ($responder->valid_id_path) {
            Storage::disk('public')->delete($responder->valid_id_path);
        }

        $responder->delete();

        return back()->with('success', "{$name}'s account was rejected and removed. A notification was sent to {$email}.");
    }
}