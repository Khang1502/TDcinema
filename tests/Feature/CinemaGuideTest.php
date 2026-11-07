<?php

namespace Tests\Feature;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CinemaGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_cinema_page_displays_active_cinemas_and_live_ticket_prices(): void
    {
        $cinema = Cinema::create([
            'name' => 'TD Cinema Trung Tâm',
            'address' => '100 Đường Điện Ảnh',
            'city' => 'TP.HCM',
            'status' => 'active',
        ]);
        Cinema::create([
            'name' => 'Rạp đang bảo trì',
            'address' => '200 Đường Khác',
            'city' => 'TP.HCM',
            'status' => 'maintenance',
        ]);
        $room = Room::create([
            'cinema_id' => $cinema->id,
            'name' => 'IMAX 1',
            'type' => 'IMAX',
            'status' => 'active',
        ]);
        $movie = Movie::create(['title' => 'Phim thử nghiệm', 'duration' => 120, 'status' => 'now_showing']);
        Showtime::create([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(120),
            'base_price' => 125000,
            'status' => 'active',
        ]);

        $this->get('/rap-gia-ve')
            ->assertOk()
            ->assertViewIs('frontend.cinemas')
            ->assertSee('TD Cinema Trung Tâm')
            ->assertSee('125.000')
            ->assertDontSee('Rạp đang bảo trì');
    }

    public function test_cinema_page_can_be_filtered_by_city(): void
    {
        Cinema::create(['name' => 'TD Cinema Sài Gòn', 'address' => 'Địa chỉ 1', 'city' => 'TP.HCM', 'status' => 'active']);
        Cinema::create(['name' => 'TD Cinema Hà Nội', 'address' => 'Địa chỉ 2', 'city' => 'Hà Nội', 'status' => 'active']);

        $this->get('/rap-gia-ve?city=Hà+Nội')
            ->assertOk()
            ->assertSee('TD Cinema Hà Nội')
            ->assertViewHas('cinemas', fn ($cinemas) => $cinemas->pluck('name')->all() === ['TD Cinema Hà Nội']);
    }

    public function test_customer_header_links_to_cinema_and_pricing_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('cinemas.index').'"', false);
    }

    public function test_selected_cinema_is_kept_until_the_customer_chooses_a_showtime(): void
    {
        $selectedCinema = Cinema::create([
            'name' => 'TD Cinema Đã Chọn',
            'address' => '01 Đường Rạp Phim',
            'city' => 'TP.HCM',
            'status' => 'active',
        ]);
        $otherCinema = Cinema::create([
            'name' => 'TD Cinema Khác',
            'address' => '02 Đường Rạp Phim',
            'city' => 'TP.HCM',
            'status' => 'active',
        ]);
        $selectedRoom = Room::create([
            'cinema_id' => $selectedCinema->id,
            'name' => 'Phòng 01',
            'type' => '2D',
            'status' => 'active',
        ]);
        $otherRoom = Room::create([
            'cinema_id' => $otherCinema->id,
            'name' => 'Phòng 02',
            'type' => '2D',
            'status' => 'active',
        ]);
        $selectedMovie = Movie::create([
            'title' => 'Phim Tại Rạp Đã Chọn',
            'duration' => 100,
            'status' => 'now_showing',
        ]);
        $otherMovie = Movie::create([
            'title' => 'Phim Chỉ Có Ở Rạp Khác',
            'duration' => 100,
            'status' => 'now_showing',
        ]);
        Showtime::create([
            'movie_id' => $selectedMovie->id,
            'room_id' => $selectedRoom->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(100),
            'base_price' => 90000,
            'status' => 'active',
        ]);
        Showtime::create([
            'movie_id' => $otherMovie->id,
            'room_id' => $otherRoom->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(100),
            'base_price' => 90000,
            'status' => 'active',
        ]);

        $movieListUrl = route('movies.index', [
            'status' => 'now_showing',
            'cinema_id' => $selectedCinema->id,
        ]);

        $this->get(route('cinemas.index'))
            ->assertOk()
            ->assertSee($movieListUrl);

        $this->get($movieListUrl)
            ->assertOk()
            ->assertViewHas('selectedCinema', fn ($cinema) => $cinema?->is($selectedCinema))
            ->assertViewHas('movies', fn ($movies) => $movies->pluck('id')->all() === [$selectedMovie->id])
            ->assertSee(route('movies.show', [
                'movie' => $selectedMovie,
                'cinema_id' => $selectedCinema->id,
            ]).'#showtimes', false);

        $this->get(route('movies.show', [
            'movie' => $selectedMovie,
            'cinema_id' => $selectedCinema->id,
        ]))
            ->assertOk()
            ->assertViewHas('selectedCinema', fn ($cinema) => $cinema?->is($selectedCinema))
            ->assertViewHas('groupedShowtimes', function (array $days) use ($selectedCinema) {
                return collect($days)
                    ->flatten(1)
                    ->every(fn ($block) => $block['cinema']->is($selectedCinema));
            })
            ->assertSee('Rạp đã chọn')
            ->assertDontSee('id="cinemaFilter"', false);
    }
}
