<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itinerary_id')->constrained('itineraries')->cascadeOnDelete();
            $table->date('day_date');
            $table->integer('day_number');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['itinerary_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_days');
    }
};
