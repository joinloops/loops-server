<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_search_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('search_url');
            $table->nullableMorphs('searchable');
            $table->timestamps();
            $table->index(
                ['profile_id', 'searchable_type', 'created_at'],
                'rsi_profile_type_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_search_imports');
    }
};
