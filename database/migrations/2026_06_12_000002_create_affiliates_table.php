<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('referral_code')->unique();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10.00);
            $table->string('commission_type')->default('percentage');
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->integer('total_clicks')->default(0);
            $table->integer('total_conversions')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('profile_id');
            $table->index('referral_code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliates');
    }
};
