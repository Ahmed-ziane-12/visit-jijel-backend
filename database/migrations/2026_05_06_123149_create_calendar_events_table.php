<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('itinerary_id')->nullable()->constrained('itineraries')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('title', 200);
            $table->text('notes')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('color', 7)->nullable();
            $table->boolean('all_day')->default(false);
            $table->enum('source', ['manual', 'itinerary', 'event'])->default('manual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
