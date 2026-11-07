<?php

use App\Models\MovieGenre;
use App\Support\MovieGenres;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movie_genres',function (Blueprint $table) {
            $table->id();
            $table->string('name',100);
            $table->string('name_key',150)->unique();
            $table->string('description',500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('aliases')->nullable();
            $table->timestamps();
        });

        $names = MovieGenres::parse([...MovieGenres::COMMON,
            ...DB::table('movies')->whereNotNull('genre')->pluck('genre')->flatMap(fn ($genre)=>MovieGenres::parse($genre))->all()]);
        $seen = [];
        foreach ($names as $name) {
            $key = MovieGenre::key($name);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            DB::table('movie_genres')->insert(['name'=>$name,'name_key'=>$key,'is_active'=>true,
                'sort_order'=>count($seen)*10,'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void { Schema::dropIfExists('movie_genres'); }
};
