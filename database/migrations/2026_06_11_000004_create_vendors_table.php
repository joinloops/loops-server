<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('profile_id');
            $table->string('business_name', 255);
            $table->string('business_email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending'); // pending, active, suspended, rejected
            $table->decimal('commission_rate', 5, 2)->default(10.00);
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('payout_method', 50)->nullable();
            $table->json('payout_details')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('profile_id');
            $table->index('status');

            $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
