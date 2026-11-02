<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item 58: evacuation centers are always open, so there's no "Open" or
 * "Closed" anymore — only "Full", which MSWD sets in Resqponder when a
 * center can't take more evacuees, and takes off when it has room again.
 *
 * status is now 'open' (has room — never shown) or 'full'. A center that
 * was marked closed becomes 'open', and 'closed' is taken out of the
 * column's choices.
 *
 * Runs by itself on deploy (php artisan migrate --force). The same by
 * hand, in phpMyAdmin → SQL:
 *
 *   UPDATE evacuation_centers SET status = 'open' WHERE status = 'closed';
 *   ALTER TABLE evacuation_centers
 *     MODIFY status ENUM('open','full') NOT NULL DEFAULT 'open';
 *
 * Running it again does no harm.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('evacuation_centers')->where('status', 'closed')->update(['status' => 'open']);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evacuation_centers MODIFY status ENUM('open','full') NOT NULL DEFAULT 'open'");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE evacuation_centers MODIFY status ENUM('open','full','closed') NOT NULL DEFAULT 'open'");
        }
    }
};
