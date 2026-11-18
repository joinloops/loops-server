<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->json('also_known_as')->nullable();
            $table->unsignedBigInteger('moved_to_profile_id')->nullable()->index();
            $table->timestamp('moved_at')->nullable();
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->json('media_hashes')->nullable();
            $table->string('previous_ap_id', 2048)->nullable();
            $table->char('previous_ap_id_hash', 64)->nullable()->index();
        });

        Schema::create('account_migrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('direction', 16);
            $table->string('state', 24);
            $table->unsignedBigInteger('profile_id')->index();
            $table->unsignedBigInteger('remote_profile_id')->nullable()->index();
            $table->string('local_actor', 2048);
            $table->string('remote_actor', 2048);
            $table->string('remote_domain')->index();
            $table->string('remote_migration_id', 2048)->nullable();
            $table->char('remote_migration_hash', 64)->nullable()->index();
            $table->json('scope')->nullable();
            $table->char('manifest_root', 64)->nullable();
            $table->unsignedInteger('manifest_items')->default(0);
            $table->unsignedBigInteger('manifest_bytes')->default(0);
            $table->char('map_root', 64)->nullable();
            $table->longText('intent_jws')->nullable();
            $table->longText('receipt_jws')->nullable();
            $table->json('seal')->nullable();
            $table->string('transit_url')->nullable();
            $table->unsignedInteger('items_total')->default(0);
            $table->unsignedInteger('items_done')->default(0);
            $table->unsignedInteger('items_failed')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);
            $table->unsignedBigInteger('bytes_done')->default(0);
            $table->json('stats')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'direction', 'state']);
            $table->index(['direction', 'state', 'created_at']);
        });

        Schema::create('account_migration_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('migration_id');
            $table->unsignedInteger('position');
            $table->string('kind', 16);
            $table->string('source_id', 2048);
            $table->char('source_hash', 64);
            $table->string('target_id', 2048)->nullable();
            $table->string('target_url', 2048)->nullable();
            $table->unsignedBigInteger('local_id')->nullable();
            $table->json('payload')->nullable();
            $table->char('leaf_hash', 64);
            $table->string('state', 16)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('error')->nullable();
            $table->timestamps();

            $table->foreign('migration_id')->references('id')->on('account_migrations')->cascadeOnDelete();
            $table->unique(['migration_id', 'position']);
            $table->index(['migration_id', 'kind', 'state']);
            $table->index(['migration_id', 'source_hash']);
            $table->index(['kind', 'local_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_migration_items');
        Schema::dropIfExists('account_migrations');

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['previous_ap_id_hash']);
            $table->dropColumn(['media_hashes', 'previous_ap_id', 'previous_ap_id_hash']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['moved_to_profile_id']);
            $table->dropColumn(['also_known_as', 'moved_to_profile_id', 'moved_at']);
        });
    }
};
