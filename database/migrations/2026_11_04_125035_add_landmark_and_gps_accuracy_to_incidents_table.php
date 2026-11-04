<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 59: report locations that are easier to find, and more accurate.
 *
 *  landmark           what the resident typed in Report Emergency's
 *                     Landmark box, e.g. "beside the chapel, blue gate"
 *  location_accuracy  how close the phone's GPS pin is, in meters
 *                     ("within about 12 m"); null from older apps
 *  location_fixed_at  when the phone got that pin — earlier than the
 *                     report when the phone had to use an older one
 *
 * (The nearest well-known place — "About 120 m from Rosales Central
 * School" — goes in the location text itself; see
 * BarangayLocationService::describe().)
 *
 * Runs by itself on deploy (php artisan migrate --force). The same columns
 * by hand, in phpMyAdmin → SQL:
 *
 *   ALTER TABLE incidents
 *     ADD landmark VARCHAR(150) NULL AFTER location,
 *     ADD location_accuracy INT UNSIGNED NULL AFTER longitude,
 *     ADD location_fixed_at TIMESTAMP NULL AFTER location_accuracy;
 *
 * If the columns were already added by hand, this migration skips them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('incidents', 'landmark')) {
                $table->string('landmark', 150)->nullable()->after('location');
            }
            if (! Schema::hasColumn('incidents', 'location_accuracy')) {
                $table->unsignedInteger('location_accuracy')->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('incidents', 'location_fixed_at')) {
                $table->timestamp('location_fixed_at')->nullable()->after('location_accuracy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            foreach (['landmark', 'location_accuracy', 'location_fixed_at'] as $column) {
                if (Schema::hasColumn('incidents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
