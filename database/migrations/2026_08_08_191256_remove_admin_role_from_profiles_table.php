<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // "Admin" accounts were previously stored in the profiles.role enum
        // without the users.is_admin flag ever being set (phantom admins).
        // Promote any legacy rows to real admins, then drop the enum value.
        DB::transaction(function () {
            $userIds = DB::table('profiles')->where('role', 'admin')->pluck('user_id');

            DB::table('users')
                ->whereIn('id', $userIds)
                ->where('is_admin', false)
                ->update(['is_admin' => true]);

            DB::table('profiles')->where('role', 'admin')->update(['role' => 'client']);
        });

        DB::statement('alter table profiles drop constraint if exists profiles_role_check');

        DB::statement("alter table profiles add constraint profiles_role_check check (role in ('business_owner', 'client'))");
    }

    public function down(): void
    {
        DB::statement('alter table profiles drop constraint if exists profiles_role_check');

        DB::statement("alter table profiles add constraint profiles_role_check check (role in ('admin', 'business_owner', 'client'))");
    }
};
