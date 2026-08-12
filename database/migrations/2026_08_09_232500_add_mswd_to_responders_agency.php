<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds MSWD (Municipal Social Welfare and Development) as a valid
 * responder agency. The Flutter registration dropdown already offers it
 * (see responder_register.dart's _agencies list), but the DB enum and
 * ResponderAuthController::register validation were never updated to
 * match, so every MSWD signup failed with "The selected agency is
 * invalid." This migration closes that gap.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE responders MODIFY agency ENUM('PNP', 'BFP', 'SARS', 'HCU', 'MSWD') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE responders MODIFY agency ENUM('PNP', 'BFP', 'SARS', 'HCU') NOT NULL");
    }
};