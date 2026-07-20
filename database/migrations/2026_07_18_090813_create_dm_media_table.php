<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dm_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->unsignedBigInteger('profile_id');
            $table->string('type', 20)->default('image');
            $table->string('mime_type')->nullable();
            $table->text('remote_url')->nullable();
            $table->text('preview_remote_url')->nullable();
            $table->string('path')->nullable();
            $table->string('preview_path')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('blurhash', 64)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('description')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('external_id')->nullable();
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamp('cached_at')->nullable();
            $table->timestamps();

            $table->index(['message_id', 'order']);
            $table->index('profile_id');
            $table->index(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dm_media');
    }
};
