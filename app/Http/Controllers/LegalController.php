<?php

namespace App\Http\Controllers;

use App\Support\Legal;

/**
 * The public legal pages (item 64): Privacy Policy, Terms and Conditions,
 * Cookie Policy and Accessibility. Anyone can open them — no sign-in —
 * because the apps, Google Play and the login page link to them.
 */
class LegalController extends Controller
{
    public function show(string $path)
    {
        $document = Legal::find($path);
        abort_if($document === null, 404);

        return view('legal', [
            'path'      => $path,
            'document'  => $document,
            'documents' => Legal::documents(),
        ]);
    }
}
