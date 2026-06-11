<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // Polymorphic — links to any model
            $table->string('model_type', 100);
            $table->unsignedBigInteger('model_id');

            // Cloudinary fields
            $table->string('cloudinary_public_id')->unique(); // e.g. "jijel/destinations/abc123"
            $table->string('url');                            // full Cloudinary URL
            $table->string('secure_url');                     // https version
            $table->string('format', 10)->nullable();         // jpg, webp, mp4 ...
            $table->string('resource_type', 20)->default('image'); // image, video, raw
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size')->nullable();   // bytes

            // Organisation
            $table->string('collection', 100)->default('default'); // cover, gallery, avatar ...
            $table->boolean('is_cover')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['model_type', 'model_id'], 'media_morphable_index');
            $table->index(['model_type', 'model_id', 'collection'], 'media_collection_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
