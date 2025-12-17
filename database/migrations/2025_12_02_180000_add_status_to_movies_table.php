<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            if (!Schema::hasColumn('movies', 'status')) {
                $table->string('status', 20)->default('upcoming')->after('release_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            if (Schema::hasColumn('movies', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
