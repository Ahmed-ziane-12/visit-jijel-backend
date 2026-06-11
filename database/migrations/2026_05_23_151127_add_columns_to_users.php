<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('remember_token');
            $table->boolean('is_super_admin')->default(false)->after('is_admin');
            $table->boolean('must_reset_password')->default(false)->after('is_super_admin');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('must_reset_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['is_admin', 'is_super_admin', 'must_reset_password', 'created_by']);
        });
    }
};
