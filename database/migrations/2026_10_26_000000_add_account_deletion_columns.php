<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->timestamp('purged_at')->nullable()->after('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['status', 'delete_after'], 'users_status_delete_after_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_status_delete_after_idx');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('purged_at');
        });
    }
};
