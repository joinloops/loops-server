<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relay_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('relay_url', 512)->unique();
            $table->string('relay_actor_url', 512)->nullable();
            $table->string('inbox_url', 512)->nullable();
            $table->string('shared_inbox_url', 512)->nullable();
            $table->enum('status', ['pending', 'active', 'rejected', 'disabled'])->default('pending');
            $table->boolean('send_public_posts')->default(true);
            $table->boolean('receive_content')->default(true);
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->unsignedInteger('total_sent')->default(0);
            $table->unsignedInteger('total_received')->default(0);
            $table->json('relay_info')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'send_public_posts']);
            $table->index(['status', 'receive_content']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relay_subscriptions');
    }
};
