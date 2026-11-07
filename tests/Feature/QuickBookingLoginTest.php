<?php

namespace Tests\Feature;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickBookingLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_quick_booking_is_kept_and_redirects_to_seat_selection_after_login(): void
    {
        [$movie, $cinema, $showtime] = $this->createShowtime();
        $customer = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'is_active' => true,
        ]);

        $selection = [
            'movie_id' => $movie->id,
            'cinema_id' => $cinema->id,
            'date' => $showtime->start_time->toDateString(),
            'showtime_id' => $showtime->id,
        ];

        $this->postJson(route('booking.quick-intent'), $selection)
            ->assertOk()
            ->assertJsonPath('selection', $selection)
            ->assertSessionHas('booking.quick_selection', $selection)
            ->assertSessionHas('url.intended', route('booking.checkout', $showtime));

        $this->from(route('home'))->post('/login', [
            'email' => $customer->email,
            'password' => 'password',
        ])->assertRedirect(route('booking.checkout', $showtime));

        $this->assertAuthenticatedAs($customer);
    }

    public function test_quick_booking_remains_available_after_a_failed_login(): void
    {
        [$movie, $cinema, $showtime] = $this->createShowtime();
        $customer = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'is_active' => true,
        ]);
        $selection = [
            'movie_id' => $movie->id,
            'cinema_id' => $cinema->id,
            'date' => $showtime->start_time->toDateString(),
            'showtime_id' => $showtime->id,
        ];

        $this->postJson(route('booking.quick-intent'), $selection)->assertOk();

        $this->from(route('home'))->post('/login', [
            'email' => $customer->email,
            'password' => 'wrong-password',
        ])->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertEquals($selection, session('booking.quick_selection'));
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-quick-selection=', false)
            ->assertSee('"showtime_id":'.$showtime->id, false);
    }

    public function test_quick_booking_rejects_a_showtime_from_another_cinema(): void
    {
        [$movie, , $showtime] = $this->createShowtime();
        $otherCinema = Cinema::create([
            'name' => 'TD Cinema Khác',
            'address' => '02 Đường Điện Ảnh',
            'city' => 'TP.HCM',
            'status' => 'active',
        ]);

        $this->postJson(route('booking.quick-intent'), [
            'movie_id' => $movie->id,
            'cinema_id' => $otherCinema->id,
            'date' => $showtime->start_time->toDateString(),
            'showtime_id' => $showtime->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('showtime_id')
            ->assertSessionMissing('booking.quick_selection')
            ->assertSessionMissing('url.intended');
    }

    private function createShowtime(): array
    {
        $cinema = Cinema::create([
            'name' => 'TD Cinema Đặt Vé Nhanh',
            'address' => '01 Đường Điện Ảnh',
            'city' => 'TP.HCM',
            'status' => 'active',
        ]);
        $room = Room::create([
            'cinema_id' => $cinema->id,
            'name' => 'Phòng 01',
            'type' => '2D',
            'status' => 'active',
        ]);
        $movie = Movie::create([
            'title' => 'Phim Đặt Vé Nhanh',
            'duration' => 110,
            'status' => 'now_showing',
        ]);
        $showtime = Showtime::create([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'start_time' => now()->addDay()->startOfHour(),
            'end_time' => now()->addDay()->startOfHour()->addMinutes(110),
            'base_price' => 100000,
            'status' => 'active',
        ]);

        return [$movie, $cinema, $showtime];
    }
}
