<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_video', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('video_id')->nullable();
            $table->unsignedBigInteger('comment_id')->nullable();
            $table->float('bounding_box_x')->nullable();
            $table->float('bounding_box_y')->nullable();
            $table->float('bounding_box_w')->nullable();
            $table->float('bounding_box_h')->nullable();
            $table->float('timestamp_start')->nullable(); // seconds
            $table->float('timestamp_end')->nullable();   // seconds
            $table->string('detection_method')->default('manual'); // manual, ai_auto, ai_suggested
            $table->float('confidence')->nullable(); // AI detection confidence 0-1
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');

            $table->index('product_id');
            $table->index('video_id');
            $table->unique(['product_id', 'video_id', 'timestamp_start'], 'prod_vid_ts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_video');
    }
};
