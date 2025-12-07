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
        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('is_duet')->default(false)->after('can_duet');
            $table->unsignedTinyInteger('duet_layout')->default(1)->after('is_duet');
            $table->foreignId('original_duet_id')->nullable()->after('duet_layout')->constrained('videos')->nullOnDelete();
            $table->string('duet_path')->unique()->nullable()->after('original_duet_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropForeign(['original_duet_id']);
            $table->dropColumn(['is_duet', 'duet_layout', 'original_duet_id', 'duet_path']);
        });
    }
};
