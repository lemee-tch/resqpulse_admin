<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responders', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('full_name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('suffix', 20)->nullable()->after('last_name');

            $table->string('verification_otp')->nullable()->after('email_verified_at');
            $table->timestamp('verification_otp_expires_at')->nullable()->after('verification_otp');
        });

        // Adds Health Care Unit as a responder agency.
        DB::statement("ALTER TABLE responders MODIFY agency ENUM('PNP', 'BFP', 'SARS', 'HCU') NOT NULL");
    }

    public function down(): void
    {
        Schema::table('responders', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'middle_name', 'last_name', 'suffix',
                'verification_otp', 'verification_otp_expires_at',
            ]);
        });

        DB::statement("ALTER TABLE responders MODIFY agency ENUM('PNP', 'BFP', 'SARS') NOT NULL");
    }
};