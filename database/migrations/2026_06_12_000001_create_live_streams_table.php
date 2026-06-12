<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending'); // pending, live, ended, cancelled
            $table->string('stream_key', 64)->unique();
            $table->integer('viewer_count')->default(0);
            $table->integer('max_viewers')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('product_ids')->nullable();
            $table->boolean('chat_enabled')->default(true);
            $table->string('recording_url')->nullable();
            $table->timestamps();

            $table->index('profile_id');
            $table->index('status');
            $table->index('stream_key');

            $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_streams');
    }
};
