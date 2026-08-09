<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->string('arabic_name', 150)->nullable()->after('name');
            $table->text('arabic_description')->nullable()->after('description');
            $table->text('arabic_address')->nullable()->after('address');
            $table->string('arabic_category')->nullable()->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn(['arabic_name', 'arabic_description', 'arabic_address', 'arabic_category']);
        });
    }
};
