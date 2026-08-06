<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('ai_detected_type')->nullable()->after('emergency_type');
            $table->string('ai_confidence')->nullable()->after('ai_detected_type');
            $table->text('ai_analysis')->nullable()->after('ai_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['ai_detected_type', 'ai_confidence', 'ai_analysis']);
        });
    }
};