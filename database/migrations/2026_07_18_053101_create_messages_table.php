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
        Schema::create('messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('profile_id');
            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();
            $table->json('entities')->nullable();
            $table->unsignedBigInteger('video_id')->nullable();
            $table->string('ap_object_uri')->nullable()->unique();
            $table->unsignedBigInteger('in_reply_to_id')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index('profile_id');
            $table->index('video_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
