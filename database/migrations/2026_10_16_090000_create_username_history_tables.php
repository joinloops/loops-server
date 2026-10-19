<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('username_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('profile_id');
            $table->string('username');
            $table->string('domain')->nullable();
            $table->boolean('local')->default(true);
            $table->string('source', 20)->default('initial');
            $table->boolean('is_revert')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'ended_at']);
            $table->index(['profile_id', 'source', 'started_at']);
            $table->index(['username', 'started_at']);
        });

        Schema::create('username_reservations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('profile_id')->unique();
            $table->string('username')->unique();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('username_history')->insertUsing(
            ['profile_id', 'username', 'domain', 'local', 'source', 'is_revert', 'started_at', 'created_at', 'updated_at'],
            DB::table('profiles')->select([
                'id',
                'username',
                DB::raw('CASE WHEN local = 1 THEN NULL ELSE domain END'),
                'local',
                DB::raw("'initial'"),
                DB::raw('0'),
                DB::raw('COALESCE(created_at, '.DB::getPdo()->quote($now->toDateTimeString()).')'),
                DB::raw(DB::getPdo()->quote($now->toDateTimeString())),
                DB::raw(DB::getPdo()->quote($now->toDateTimeString())),
            ])
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('username_reservations');
        Schema::dropIfExists('username_history');
    }
};
