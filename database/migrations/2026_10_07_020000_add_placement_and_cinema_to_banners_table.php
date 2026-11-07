<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('placement', 16)->default('home')->index();
            // Older local cinema tables use MyISAM; clean up references in the model.
            $table->unsignedBigInteger('cinema_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex(['placement']);
            $table->dropIndex(['cinema_id']);
            $table->dropColumn(['placement', 'cinema_id']);
        });
    }
};
