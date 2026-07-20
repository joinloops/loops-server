<?php

use App\Models\Profile;
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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_dm')->default(true);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('can_dm')->default(true);
            $table->string('dm_privacy')->default('following');
        });

        Profile::where('local', false)->update([
            'dm_privacy' => 'everyone',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_dm']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['can_dm', 'dm_privacy']);
        });
    }
};
