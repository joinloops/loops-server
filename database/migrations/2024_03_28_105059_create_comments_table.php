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
        Schema::create('comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('video_id')->index();
            $table->unsignedBigInteger('profile_id');
            $table->text('caption')->nullable();
            $table->json('entities')->nullable();
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('replies')->default(0);
            $table->boolean('can_reply')->default(true);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_sensitive')->default(false);

            $table->foreign('video_id')->references('id')->on('videos')->cascadeOnDelete();
            $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};