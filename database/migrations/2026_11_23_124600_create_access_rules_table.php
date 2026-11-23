<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('match_type', 16);
            $table->string('value', 255);
            $table->string('ip_start', 32)->nullable();
            $table->string('ip_end', 32)->nullable();
            $table->boolean('blocks_login')->default(true);
            $table->boolean('blocks_registration')->default(true);
            $table->string('note', 500)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('source_profile_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['type', 'match_type', 'value']);
            $table->index(['type', 'id']);
            $table->index(['ip_start', 'ip_end']);
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasIndex('users', 'users_register_ip_index')) {
                $table->index('register_ip');
            }

            if (! Schema::hasIndex('users', 'users_last_ip_index')) {
                $table->index('last_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasIndex('users', 'users_register_ip_index')) {
                $table->dropIndex('users_register_ip_index');
            }

            if (Schema::hasIndex('users', 'users_last_ip_index')) {
                $table->dropIndex('users_last_ip_index');
            }
        });

        Schema::dropIfExists('access_rules');
    }
};
