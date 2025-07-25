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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('profile_id')->nullable()->unique()->index();
            $table->string('username')->unique()->index();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedTinyInteger('role')->default(1);
            $table->boolean('is_admin')->default(false);
            $table->boolean('has_2fa')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->json('two_factor_backups')->nullable();
            $table->boolean('can_upload')->default(true);
            $table->boolean('can_comment')->default(true);
            $table->boolean('can_like')->default(true);
            $table->boolean('can_follow')->default(true);
            $table->text('admin_note')->nullable();
            $table->unsignedTinyInteger('trust_level')->default(5)->nullable();
            $table->timestamp('delete_after')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
