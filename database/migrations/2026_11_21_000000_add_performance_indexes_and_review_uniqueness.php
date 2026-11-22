<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->index(['is_active', 'is_verified'], 'businesses_active_verified_index');
            $table->index('type', 'businesses_type_index');
            $table->index('wilaya', 'businesses_wilaya_index');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->index('status', 'listings_status_index');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index('status', 'events_status_index');
            $table->index('starts_at', 'events_starts_at_index');
        });

        // Older rows were written while the Like model had $timestamps = false.
        DB::table('likes')->whereNull('created_at')->update(['created_at' => now()]);

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Keep the earliest review when the same user reviewed the same
        // subject more than once, then enforce it at the database level.
        DB::statement(<<<'SQL'
            DELETE FROM reviews a
            USING reviews b
            WHERE a.id > b.id
              AND a.user_id = b.user_id
              AND a.listing_id IS NOT DISTINCT FROM b.listing_id
              AND a.destination_id IS NOT DISTINCT FROM b.destination_id
              AND a.event_id IS NOT DISTINCT FROM b.event_id
        SQL);

        DB::statement(
            'CREATE UNIQUE INDEX reviews_user_listing_unique
             ON reviews (user_id, listing_id)
             WHERE listing_id IS NOT NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX reviews_user_destination_unique
             ON reviews (user_id, destination_id)
             WHERE destination_id IS NOT NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX reviews_user_event_unique
             ON reviews (user_id, event_id)
             WHERE event_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex('businesses_active_verified_index');
            $table->dropIndex('businesses_type_index');
            $table->dropIndex('businesses_wilaya_index');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_status_index');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_status_index');
            $table->dropIndex('events_starts_at_index');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS reviews_user_listing_unique');
        DB::statement('DROP INDEX IF EXISTS reviews_user_destination_unique');
        DB::statement('DROP INDEX IF EXISTS reviews_user_event_unique');
    }
};
