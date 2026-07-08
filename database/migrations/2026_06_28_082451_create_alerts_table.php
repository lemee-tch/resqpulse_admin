<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable(); // e.g. "Rosales, Pangasinan"
            $table->text('body');
            $table->enum('type', ['Alerts', 'Updates'])->default('Alerts');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // which admin sent it
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};