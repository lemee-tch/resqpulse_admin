<?php

namespace Database\Seeders;

use App\Models\Responder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Pre-builds ONE shared login per responder agency — there is no
 * self-service registration anymore (see api.php: the responder
 * register/verify-email/confirm-registration routes are no longer
 * exposed). Any number of staff within an agency log into this same
 * account on their own devices simultaneously (Sanctum already supports
 * concurrent sessions per account — see ResponderAuthController::login()).
 *
 * Each agency gets its OWN randomly generated password — not a shared
 * default — printed once to the console table below so you can copy it
 * out immediately. The plaintext is never stored anywhere (only the hash
 * goes to the DB), so if you lose the console output before writing it
 * down, use the admin panel's "Responder Accounts" page to reset that
 * agency's password instead of re-running this seeder.
 *
 * Re-running this seeder regenerates a NEW random password for every
 * agency (updateOrCreate re-sets the password field every time), which
 * will sign every currently-logged-in device out. Don't run this against
 * a live database casually — it's meant for first-time setup.
 */
class ResponderSeeder extends Seeder
{
    public function run(): void
    {
        $agencies = [
            'PNP'  => ['label' => 'Philippine National Police',     'email' => 'pnp.rosales@resqpulse.local'],
            'BFP'  => ['label' => 'Bureau of Fire Protection',      'email' => 'bfp.rosales@resqpulse.local'],
            'SARS' => ['label' => 'Search and Rescue',              'email' => 'sars.rosales@resqpulse.local'],
            'HCU'  => ['label' => 'Health Care Unit',               'email' => 'hcu.rosales@resqpulse.local'],
            'MSWD' => ['label' => 'Municipal Social Welfare & Dev.','email' => 'mswd.rosales@resqpulse.local'],
        ];

        $credentialsForDisplay = [];

        foreach ($agencies as $code => $info) {
            $plainPassword = Str::password(12); // unique per agency, e.g. "kR7!qLpZ9xVt"

            Responder::updateOrCreate(
                ['agency' => $code],
                [
                    'full_name'           => $info['label'] . ' — Rosales',
                    'first_name'          => $info['label'],
                    'last_name'           => 'Rosales',
                    'badge_number'        => $code . '-ROSALES-01',
                    'email'               => $info['email'],
                    // Plain text here on purpose — Responder::$password is
                    // cast as 'hashed', so Eloquent hashes it automatically
                    // on save. Calling Hash::make() here too would hash it
                    // twice and break login with a "does not use the
                    // Bcrypt algorithm" error when the cast tries to
                    // process an already-hashed string a second time.
                    'password'            => $plainPassword,
                    'mobile'              => null,
                    'unit_station'        => null,
                    'status'              => 'active',
                    'verification_status' => 'verified',
                    'verified_at'         => now(),
                    'email_verified_at'   => now(),
                ]
            );

            $credentialsForDisplay[] = [$code, $info['email'], $plainPassword];
        }

        if ($this->command) {
            $this->command->newLine();
            $this->command->warn('Responder agency credentials — copy these now, they will NOT be shown again:');
            $this->command->table(['Agency', 'Email', 'Password'], $credentialsForDisplay);
            $this->command->newLine();
        }
    }
}