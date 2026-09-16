<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes 'moderate' as a valid incident priority — MDRRMO is keeping
 * priority a simple 3-tier scale: critical, high, low. Existing rows
 * with priority = 'moderate' are remapped to 'low' before narrowing the
 * enum — can't apply a narrower ENUM while a row still holds a value
 * outside it (MySQL strict mode rejects that with "Data truncated").
 * Same widen → remap → narrow order as the other ENUM migrations here.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE incidents MODIFY COLUMN priority VARCHAR(20) NULL");

        DB::table('incidents')->where('priority', 'moderate')->update(['priority' => 'low']);

        DB::statement("ALTER TABLE incidents MODIFY COLUMN priority ENUM('critical','high','low') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE incidents MODIFY COLUMN priority ENUM('critical','high','moderate','low') NULL");
    }
};