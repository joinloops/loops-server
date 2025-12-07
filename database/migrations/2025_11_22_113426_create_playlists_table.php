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
        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('visibility', ['public', 'unlisted', 'private', 'followers']);
            $table->string('cover_image')->nullable();
            $table->unsignedInteger('videos_count')->default(0);
            $table->timestamps();

            $table->index(['profile_id', 'created_at']);
        });

        Schema::create('playlist_video', function (Blueprint $table) {
            $table->foreignId('playlist_id')->constrained()->onDelete('cascade');
            $table->foreignId('video_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->primary(['playlist_id', 'video_id']);
            $table->unique('video_id');
            $table->index('position');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->boolean('has_playlists')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['has_playlists']);
        });
        Schema::dropIfExists('playlist_video');
        Schema::dropIfExists('playlists');
    }
};
