<?php

namespace Tests\Feature;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncGalaxyShowtimesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_maps_galaxy_time_to_all_local_cinemas_without_duplicates(): void
    {
        $cinema = Cinema::create([
            'name' => 'TD Cinema Trung Tâm',
            'address' => 'TP.HCM',
            'city' => 'TP.HCM',
            'status' => 'active',
        ]);
        $imaxRoom = Room::create([
            'cinema_id' => $cinema->id,
            'name' => 'IMAX 01',
            'type' => 'IMAX',
            'status' => 'active',
        ]);
        $otherCinema = Cinema::create([
            'name' => 'TD Cinema Đà Lạt',
            'address' => 'Đà Lạt',
            'city' => 'Đà Lạt',
            'status' => 'active',
        ]);
        Room::create([
            'cinema_id' => $otherCinema->id,
            'name' => 'Phòng 01',
            'type' => '2D',
            'status' => 'active',
        ]);
        $movie = Movie::create([
            'title' => 'Phim Galaxy Có Lịch',
            'duration' => 120,
            'status' => 'now_showing',
            'source' => 'galaxycine',
            'external_id' => 'movie-source-1',
        ]);
        $start = now('Asia/Ho_Chi_Minh')->addDay()->setTime(19, 30, 0);

        Http::fake([
            '*sessions2*' => Http::response([
                'data' => [
                    'result' => [[
                        'id' => 'galaxy-session-1',
                        'showDate' => $start->toDateString(),
                        'showTime' => $start->format('H:i'),
                        'screenName' => 'RẠP IMAX',
                        'version' => 'imax',
                        'movieFormat' => 'IMAX Phụ Đề',
                        'cinema' => [
                            'id' => 'galaxy-cinema-1',
                            'name' => 'Galaxy Nguyễn Du',
                            'address' => '116 Nguyễn Du, TP.HCM',
                        ],
                    ]],
                ],
            ]),
        ]);

        $this->artisan('movies:sync-galaxy-showtimes', ['--days' => 2])
            ->assertSuccessful();
        $this->artisan('movies:sync-galaxy-showtimes', ['--days' => 2])
            ->assertSuccessful();

        $this->assertSame(2, Showtime::where('source', 'galaxycine')->count());
        $this->assertSame(2, Showtime::where('source', 'galaxycine')->distinct('room_id')->count('room_id'));
        $showtime = Showtime::where('source', 'galaxycine')->where('room_id', $imaxRoom->id)->firstOrFail();
        $this->assertSame($movie->id, $showtime->movie_id);
        $this->assertSame($imaxRoom->id, $showtime->room_id);
        $this->assertSame($start->format('Y-m-d H:i'), $showtime->start_time->format('Y-m-d H:i'));
        $this->assertSame('galaxy-session-1@'.$cinema->id, $showtime->external_id);
        $expectedPrice = 110000 + ($start->isWeekend() ? 10000 : 0) + ($start->hour >= 18 ? 10000 : 0);
        $this->assertSame((float) $expectedPrice, (float) $showtime->base_price);
    }
}
