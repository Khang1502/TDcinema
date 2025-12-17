<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Review;
use App\Models\User;
use App\Models\Movie;

class ReviewsSeeder extends Seeder
{
    public function run(): void
    {
        Review::truncate();

        $user  = User::where('email', 'customer@example.com')->first();
        $movie = Movie::first();

        if (! $user || ! $movie) {
            return;
        }

        Review::create([
            'user_id'  => $user->id,
            'movie_id' => $movie->id,
            'rating'   => 5,
            'comment'  => 'Phim rất hay, âm thanh hình ảnh cực đã!',
        ]);
    }
}
