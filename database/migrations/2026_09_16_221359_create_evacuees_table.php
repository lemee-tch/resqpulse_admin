<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evacuees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evacuation_center_id')->constrained()->cascadeOnDelete();

            // One row per person ("per head"), not per household — so
            // first/middle/last/suffix are the individual's own name,
            // not a household head with a headcount number.
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();

            $table->string('contact_number')->nullable();
            $table->string('barangay');
            $table->enum('gender', ['Male', 'Female']);
            $table->unsignedTinyInteger('age');

            // Which MSWD responder logged this entry, in the field —
            // accountability for who recorded it. Nullable so a deleted
            // responder account doesn't break historical log rows.
            $table->foreignId('logged_by')->nullable()->constrained('responders')->nullOnDelete();

            $table->timestamps();

            $table->index('barangay');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evacuees');
    }
};