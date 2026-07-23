<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('participants_hash')->nullable()->change();
            $table->string('context_uri')->nullable()->change();
        });

        if (! Schema::hasColumn('conversations', 'title')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('title')->nullable()->after('type');
            });
        }

        if (! Schema::hasIndex('conversations', 'conversations_context_uri_unique')) {
            DB::table('conversations')
                ->select('context_uri', DB::raw('MIN(id) as keep_id'))
                ->whereNotNull('context_uri')
                ->groupBy('context_uri')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->each(function ($row) {
                    DB::table('conversations')
                        ->where('context_uri', $row->context_uri)
                        ->where('id', '!=', $row->keep_id)
                        ->update(['context_uri' => null]);
                });

            Schema::table('conversations', function (Blueprint $table) {
                $table->unique('context_uri');
            });
        }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasIndex('conversations', 'conversations_context_uri_unique')) {
                $table->dropUnique(['context_uri']);
            }

            if (Schema::hasColumn('conversations', 'title')) {
                $table->dropColumn('title');
            }
        });
    }
};
