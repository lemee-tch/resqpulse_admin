<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_declines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responder_id')->constrained()->cascadeOnDelete();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->unique(['incident_id', 'responder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_declines');
    }
};
