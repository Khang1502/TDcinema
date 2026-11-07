<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('footer_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_visible')->default(true);
            $table->string('brand_name', 120);
            $table->string('company_name', 200);
            $table->text('description')->nullable();
            $table->string('address', 300)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('support_title', 80);
            $table->string('editorial_title', 80);
            $table->string('contact_title', 80);
            $table->string('copyright', 300);
            $table->json('support_links');
            $table->json('editorial_links');
            foreach (['facebook_url', 'instagram_url', 'youtube_url'] as $field) $table->string($field, 2048)->nullable();
            $table->timestamps();
        });
        $defaults = \App\Models\FooterSetting::DEFAULTS;
        foreach (['support_links', 'editorial_links'] as $key) $defaults[$key] = json_encode($defaults[$key], JSON_UNESCAPED_UNICODE);
        DB::table('footer_settings')->insert(['id' => 1, ...$defaults, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('footer_settings');
    }
};
