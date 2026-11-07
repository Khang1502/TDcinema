<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->boolean('is_imax')->default(false)->after('status');
            $table->string('source', 30)->nullable()->after('is_imax');
            $table->string('external_id', 64)->nullable()->after('source');
            $table->string('source_url')->nullable()->after('external_id');
            $table->unique(['source', 'external_id'], 'movies_source_external_unique');
        });

        DB::table('movies')->where('status', 'imax')->update(['is_imax' => true]);
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropUnique('movies_source_external_unique');
            $table->dropColumn(['is_imax', 'source', 'external_id', 'source_url']);
        });
    }
};
