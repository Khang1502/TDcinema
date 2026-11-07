<?php

namespace Tests\Feature;

use App\Models\{Cinema, Movie, Room, Seat, Showtime, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BookingHoldDeadlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_seat_keeps_one_deadline_across_checkout_combo_and_payment(): void
    {
        $this->travelTo(now()->startOfSecond());
        $cinema = Cinema::create(['name' => 'Rạp kiểm thử', 'address' => 'Test', 'city' => 'TP.HCM']);
        $room = Room::create(['cinema_id' => $cinema->id, 'name' => 'Rạp 1', 'type' => '2D', 'total_seats' => 2]);
        foreach ([1, 2] as $number) {
            Seat::create(['room_id' => $room->id, 'seat_row' => 'A', 'seat_number' => $number,
                'seat_type' => 'normal', 'extra_price' => 0, 'status' => 1]);
        }
        $movie = Movie::create(['title' => 'Phim kiểm thử', 'duration' => 90, 'status' => 'now_showing']);
        $showtime = Showtime::create(['movie_id' => $movie->id, 'room_id' => $room->id,
            'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addMinutes(90),
            'base_price' => 80000, 'status' => 'active']);
        $this->actingAs(User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]));

        $deadline = $this->postJson(route('booking.hold', $showtime), ['seats' => ['A1']])
            ->assertOk()->json('expires_at');
        $this->travel(8)->minutes();
        $this->postJson(route('booking.hold', $showtime), ['seats' => ['A1', 'A2']])
            ->assertOk()->assertJsonPath('expires_at', $deadline)->assertSessionHas('booking.seats', 'A1,A2');

        foreach (['booking.checkout', 'booking.combo', 'booking.payment'] as $route) {
            $this->get(route($route, $showtime))->assertOk()->assertViewHas('holdExpiresAt', $deadline)
                ->assertSee('data-hold-expires-at="'.$deadline.'"', false);
        }

        $this->travel(2)->minutes();
        $this->get(route('booking.combo', $showtime))->assertRedirect(route('booking.checkout', $showtime))
            ->assertSessionMissing('booking.hold');
        $this->get(route('booking.payment', $showtime))->assertRedirect(route('booking.checkout', $showtime));
        $this->assertNull(Cache::get("booking-hold:{$showtime->id}:A1"));
        $this->assertNull(Cache::get("booking-hold:{$showtime->id}:A2"));
        $this->assertDatabaseCount('tickets', 0);
    }
}
