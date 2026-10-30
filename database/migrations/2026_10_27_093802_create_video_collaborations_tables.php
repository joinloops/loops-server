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
        Schema::create('video_collaborations', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('video_id');
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('inviter_profile_id');
            $table->unsignedTinyInteger('state')->default(0);
            $table->boolean('is_local')->default(true);
            $table->boolean('auto_approved')->default(false);
            $table->string('attestation_url', 2048)->nullable();
            $table->char('attestation_hash', 64)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['video_id', 'profile_id']);
            $table->index(['profile_id', 'state', 'video_id']);
            $table->index(['inviter_profile_id', 'state']);
            $table->index(['state', 'expires_at']);
            $table->index('attestation_hash');
        });

        Schema::create('collab_trusts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('trusted_profile_id');
            $table->timestamps();

            $table->unique(['profile_id', 'trusted_profile_id']);
            $table->index('trusted_profile_id');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->string('collab_state', 16)->default('following');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_collaborations');
        Schema::dropIfExists('collab_trusts');

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('collab_state');
        });
    }
};
