<?php
// database/migrations/2026_09_03_000000_add_responder_id_to_incidents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->foreignId('responder_id')->nullable()->after('citizen_id')
                ->constrained('responders')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->after('responder_id');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responder_id');
            $table->dropColumn('accepted_at');
        });
    }
};