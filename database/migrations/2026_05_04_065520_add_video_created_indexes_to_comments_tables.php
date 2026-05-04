<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->index(['video_id', 'created_at'], 'comments_video_created_idx');
        });

        Schema::table('comment_replies', function (Blueprint $table) {
            $table->index(['video_id', 'created_at'], 'comment_replies_video_created_idx');
        });

        Schema::table('video_likes', function (Blueprint $table) {
            $table->index(['video_id', 'created_at'], 'video_likes_video_created_idx');
        });

        Schema::table('video_reposts', function (Blueprint $table) {
            $table->index(['video_id', 'created_at'], 'video_reposts_video_created_idx');
        });

        Schema::table('feed_impressions', function (Blueprint $table) {
            $table->index(['video_id', 'viewed_at'], 'feed_impressions_video_viewed_idx');
            $table->dropIndex('feed_impressions_video_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_video_created_idx');
        });

        Schema::table('comment_replies', function (Blueprint $table) {
            $table->dropIndex('comment_replies_video_created_idx');
        });

        Schema::table('video_likes', function (Blueprint $table) {
            $table->dropIndex('video_likes_video_created_idx');
        });

        Schema::table('video_reposts', function (Blueprint $table) {
            $table->dropIndex('video_reposts_video_created_idx');
        });

        Schema::table('feed_impressions', function (Blueprint $table) {
            $table->index('video_id');
            $table->dropIndex('feed_impressions_video_viewed_idx');
        });
    }
};
