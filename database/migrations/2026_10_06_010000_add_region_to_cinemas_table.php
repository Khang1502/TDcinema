<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cinemas', function (Blueprint $table) {
            $table->string('region', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('cinemas', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->dropColumn('region');
        });
    }
};
