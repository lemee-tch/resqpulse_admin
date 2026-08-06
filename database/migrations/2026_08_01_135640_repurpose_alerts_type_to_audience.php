<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "Type" field on a broadcast is now a simple audience switch:
 * Citizens, Responders, or Both.
 *
 * The column is still ENUM('Alerts','Updates') at this point, so we
 * can't write 'Citizens' into it directly — that value isn't legal
 * for the *old* enum either and MySQL rejects it under strict mode.
 * Widen to VARCHAR first (accepts any string), remap the data, then
 * narrow down to the final enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE alerts MODIFY COLUMN type VARCHAR(20) NOT NULL DEFAULT 'Citizens'");

        DB::table('alerts')->update(['type' => 'Citizens']);

        DB::statement(
            "ALTER TABLE alerts MODIFY COLUMN type ENUM('Citizens','Responders','Both') NOT NULL DEFAULT 'Citizens'"
        );
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE alerts MODIFY COLUMN type VARCHAR(20) NOT NULL DEFAULT 'Alerts'");

        DB::table('alerts')->update(['type' => 'Alerts']);

        DB::statement(
            "ALTER TABLE alerts MODIFY COLUMN type ENUM('Alerts','Updates') NOT NULL DEFAULT 'Alerts'"
        );
    }
};