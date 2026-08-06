<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE responders MODIFY agency ENUM('PNP', 'BFP', 'SARS') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE responders MODIFY agency ENUM('PNP', 'BFP', 'MDRRMO') NOT NULL");
    }
};