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
        Schema::table('media_attachments', function (Blueprint $table) {
            $table->string('provider', 20)->nullable()->after('storage_driver');
            $table->string('external_id')->nullable()->after('provider');
            $table->index(['provider', 'external_id'], 'provider_external_id_index');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->boolean('has_media')->default(0)->index()->after('is_hidden');
            $table->unsignedTinyInteger('media_count')->default(0)->after('has_media');
        });

        Schema::table('comment_replies', function (Blueprint $table) {
            $table->boolean('has_media')->default(0)->index()->after('is_hidden');
            $table->unsignedTinyInteger('media_count')->default(0)->after('has_media');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_attachments', function (Blueprint $table) {
            $table->dropIndex('provider_external_id_index');
            $table->dropColumn('provider');
            $table->dropColumn('external_id');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('has_media');
            $table->dropColumn('media_count');
        });

        Schema::table('comment_replies', function (Blueprint $table) {
            $table->dropColumn('has_media');
            $table->dropColumn('media_count');
        });
    }
};
