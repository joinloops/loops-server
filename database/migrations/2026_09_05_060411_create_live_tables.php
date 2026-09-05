<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_channels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('profile_id')->unique();
            $table->uuid('public_id')->unique();
            $table->string('stream_key', 64)->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('visibility')->default(1);
            $table->boolean('chat_enabled')->default(true);
            $table->string('chat_mode', 20)->default('everyone');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('key_rotated_at')->nullable();
            $table->unsignedBigInteger('current_stream_id')->nullable();
            $table->timestamps();
        });

        Schema::create('live_streams', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('live_channel_id')->index();
            $table->unsignedBigInteger('profile_id')->index();
            $table->string('status', 20)->default('preparing')->index();
            $table->string('title')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->unsignedTinyInteger('visibility')->default(1);
            $table->string('source_id')->nullable();
            $table->unsignedInteger('peak_viewers')->default(0);
            $table->unsignedInteger('total_viewers')->default(0);
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->unsignedBigInteger('replay_video_id')->nullable();
            $table->string('ap_id')->nullable()->unique();
            $table->string('context_uri')->nullable();
            $table->timestamps();
            $table->index(['status', 'started_at']);
        });

        Schema::create('live_stream_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('live_stream_id');
            $table->unsignedBigInteger('profile_id')->index();
            $table->unsignedBigInteger('seq')->default(0);
            $table->string('type', 20)->default('message');
            $table->text('body')->nullable();
            $table->json('entities')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['live_stream_id', 'seq']);
        });

        Schema::create('live_channel_bans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('live_channel_id');
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('moderator_id');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['live_channel_id', 'profile_id']);
        });

        Schema::create('live_channel_moderators', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('live_channel_id');
            $table->unsignedBigInteger('profile_id');
            $table->timestamps();
            $table->unique(['live_channel_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_channel_moderators');
        Schema::dropIfExists('live_channel_bans');
        Schema::dropIfExists('live_stream_messages');
        Schema::dropIfExists('live_streams');
        Schema::dropIfExists('live_channels');
    }
};
