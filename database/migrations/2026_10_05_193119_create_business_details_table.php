<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->unique()
                ->constrained('businesses')
                ->cascadeOnDelete();

            // General business information
            $table->decimal('average_price', 10, 2)->nullable();
            $table->string('price_unit', 50)->nullable();

            // Hotel
            $table->unsignedInteger('number_of_rooms')->nullable();
            $table->unsignedTinyInteger('star_rating')->nullable();

            // Restaurant
            $table->string('cuisine_type', 100)->nullable();
            $table->unsignedInteger('seating_capacity')->nullable();

            // Flexible characteristics
            $table->json('amenities')->nullable();
            $table->json('services')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_details');
    }
};
