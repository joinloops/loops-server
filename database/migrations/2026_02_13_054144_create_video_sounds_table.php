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
        Schema::create('video_sounds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('fingerprint');
            $table->string('fingerprint_hash', 64)->unique();
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedSmallInteger('duration')->default(0);
            $table->unsignedBigInteger('original_video_id')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->unsignedBigInteger('usage_count')->default(0);
            $table->boolean('is_original')->default(false);
            $table->boolean('allow_reuse')->default(false);
            $table->timestamps();
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedBigInteger('sound_id')->nullable()->index()->after('is_local');
            $table->boolean('audio_allow_reuse')->default(false);
            $table->timestamp('audio_processed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('sound_id');
            $table->dropColumn('audio_allow_reuse');
            $table->dropColumn('audio_processed_at');
        });

        Schema::dropIfExists('video_sounds');
    }
};
