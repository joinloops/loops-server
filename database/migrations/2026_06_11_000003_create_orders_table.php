<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buyer_profile_id');
            $table->unsignedBigInteger('seller_profile_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('video_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending, paid, processing, shipped, delivered, cancelled, refunded
            $table->string('payment_method')->nullable(); // stripe, bank_transfer, etc
            $table->string('payment_id')->nullable();     // external payment gateway ID
            $table->json('shipping_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products');
            $table->index('buyer_profile_id');
            $table->index('seller_profile_id');
            $table->index('status');
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
