<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Room;
use Carbon\Carbon;

class MoviesShowtimesSeeder extends Seeder
{
    public function run(): void
    {
        Showtime::truncate();
        Movie::truncate();

        $movie1 = Movie::create([
            'title'        => 'Dune: Part Two',
            'poster_path'  => 'movies/posters/dune2.jpg',
            'banner_path'  => 'movies/banners/dune2-banner.jpg',
            'description'  => 'Epic sci-fi trên hành tinh Arrakis.',
            'genre'        => 'Sci-fi',
            'duration'     => 165,
            'director'     => 'Denis Villeneuve',
            'actors'       => 'Timothée Chalamet, Zendaya',
            'trailer_url'  => 'https://youtube.com/...',
            'release_date' => Carbon::now()->subDays(10),
            'rating_avg'   => 4.6,
            'review_count' => 0,
        ]);

        $movie2 = Movie::create([
            'title'        => 'Kung Fu Panda 4',
            'poster_path'  => 'movies/posters/kfp4.jpg',
            'banner_path'  => 'movies/banners/kfp4-banner.jpg',
            'description'  => 'Panda Po trở lại với nhiệm vụ mới.',
            'genre'        => 'Animation',
            'duration'     => 110,
            'director'     => 'Mike Mitchell',
            'actors'       => 'Jack Black',
            'trailer_url'  => 'https://youtube.com/...',
            'release_date' => Carbon::now()->subDays(5),
            'rating_avg'   => 4.3,
            'review_count' => 0,
        ]);

        $rooms = Room::all();
        $today = Carbon::today();

        foreach ($rooms as $room) {
            // 3 suất chiếu cho mỗi phim
            $times = ['10:00', '14:00', '19:30'];

            foreach ($times as $time) {
                Showtime::create([
                    'movie_id'   => $movie1->id,
                    'room_id'    => $room->id,
                    'start_time' => Carbon::parse($today->toDateString() . ' ' . $time),
                    'end_time'   => Carbon::parse($today->toDateString() . ' ' . $time)->addMinutes($movie1->duration),
                    'base_price' => 90000,
                    'status'     => 'active',
                ]);
            }

            foreach ($times as $time) {
                Showtime::create([
                    'movie_id'   => $movie2->id,
                    'room_id'    => $room->id,
                    'start_time' => Carbon::parse($today->toDateString() . ' ' . $time)->addDay(),
                    'end_time'   => Carbon::parse($today->toDateString() . ' ' . $time)->addDay()->addMinutes($movie2->duration),
                    'base_price' => 80000,
                    'status'     => 'active',
                ]);
            }
        }
    }
}
