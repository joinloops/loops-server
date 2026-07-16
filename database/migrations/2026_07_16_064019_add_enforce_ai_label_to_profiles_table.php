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
        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('enforce_ai_label')->default(false);
            $table->boolean('enforce_ad_label')->default(false);
            $table->boolean('enforce_nsfw_label')->default(false);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('enforce_ai_label')->default(false);
            $table->boolean('enforce_ad_label')->default(false);
            $table->boolean('enforce_nsfw_label')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['enforce_ai_label', 'enforce_ad_label', 'enforce_nsfw_label']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['enforce_ai_label', 'enforce_ad_label', 'enforce_nsfw_label']);
        });
    }
};
