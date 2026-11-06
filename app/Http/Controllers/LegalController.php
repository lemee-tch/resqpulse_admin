<?php

namespace App\Http\Controllers;

use App\Support\Legal;

/**
 * The public legal pages (items 64–65): Privacy Policy, Terms and
 * Conditions, Cookie Policy and Accessibility — ResQPulse's (the residents'
 * app and this website) and Resqponder's (the response teams' app), each
 * the same text as its app. Anyone can open them — no sign-in — because
 * the apps, Google Play and the login page link to them.
 */
class LegalController extends Controller
{
    public function show(string $path)
    {
        $found = Legal::find($path);
        abort_if($found === null, 404);

        $sets = Legal::sets();
        $set = $sets[$found['set']];

        return view('legal', [
            'path'      => $path,
            'setKey'    => $found['set'],
            'set'       => $set,
            'document'  => $found['document'],
            'documents' => $set['documents'],
            // The other set, linked under the menu.
            'otherSet'  => $found['set'] === 'resqpulse' ? $sets['resqponder'] : $sets['resqpulse'],
            'otherPath' => $found['set'] === 'resqpulse' ? 'resqponder/privacy-policy' : 'privacy-policy',
        ]);
    }
}
