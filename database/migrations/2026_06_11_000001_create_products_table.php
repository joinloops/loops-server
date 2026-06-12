<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->json('images')->nullable();
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->integer('stock')->default(0);
            $table->string('status')->default('active'); // active, inactive, sold_out, deleted
            $table->string('external_url')->nullable();
            $table->timestamps();

            $table->index('profile_id');
            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
