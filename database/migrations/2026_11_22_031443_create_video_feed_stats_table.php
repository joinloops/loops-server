<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_feed_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('video_id')->primary();
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('completions')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->foreign('video_id')
                ->references('id')
                ->on('videos')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_feed_stats');
    }
};
