<?php

namespace Tests\Feature;

use App\Models\{Cinema, Movie, Room, Seat, Showtime, Ticket, User};
use App\Services\{BookingHoldService, BookingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminSeatPricingTest extends TestCase
{
    use RefreshDatabase;

    private function room(?Cinema $cinema = null): Room
    {
        $cinema ??= Cinema::create(['name' => 'Rạp test ' . Cinema::count(), 'address' => 'Test', 'city' => 'HCM']);
        $room = Room::create(['cinema_id' => $cinema->id, 'name' => 'Phòng test ' . Room::count(), 'type' => '2D', 'total_seats' => 3]);
        foreach (['normal', 'vip', 'sweetbox'] as $index => $type) {
            Seat::create(['room_id' => $room->id, 'seat_row' => 'A', 'seat_number' => $index + 1,
                'seat_type' => $type, 'extra_price' => 0, 'status' => 1]);
        }
        return $room;
    }

    private function showtime(Room $room): Showtime
    {
        $movie = Movie::create(['title' => 'Phim test', 'duration' => 90, 'status' => 'now_showing']);
        return Showtime::create(['room_id' => $room->id, 'movie_id' => $movie->id,
            'start_time' => now()->addHour(), 'end_time' => now()->addHours(3), 'base_price' => 80000, 'status' => 'active']);
    }

    private function fees(int $normal = 0): array
    {
        return ['normal' => $normal, 'vip' => 20000, 'sweetbox' => 50000];
    }

    public function test_prices_are_used_in_checkout_and_totals_without_changing_sold_tickets(): void
    {
        $room = $this->room();
        $showtime = $this->showtime($room);
        $seat = $room->seats()->where('seat_type', 'normal')->first();
        $ticket = Ticket::create(['user_id' => User::factory()->create()->id, 'showtime_id' => $showtime->id,
            'seat_id' => $seat->id, 'price' => 80000, 'status' => 'confirmed', 'booked_at' => now()]);
        $ids = $room->seats()->pluck('id')->all();
        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->get(route('admin.seats.index', ['cinema_id' => $room->cinema_id, 'room_id' => $room->id]))
            ->assertOk()->assertSee('Giá vé theo loại ghế')->assertSee('Lưu giá theo loại ghế');
        $this->patch(route('admin.seats.type-prices', $room), ['scope' => 'room', 'fees' => $this->fees(5000)])
            ->assertRedirect(route('admin.seats.index', ['cinema_id' => $room->cinema_id, 'room_id' => $room->id]))
            ->assertSessionHas('success');
        $this->assertSame($ids, $room->seats()->pluck('id')->all());
        $this->assertEquals(80000, $ticket->fresh()->price);
        $this->assertEquals(3, $room->fresh()->total_seats);

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('booking.checkout', $showtime))->assertOk()
            ->assertViewHas('seatRows', function ($rows) {
                return $rows->flatten(1)->pluck('price', 'code')->all() === ['A1' => 85000.0, 'A2' => 100000.0, 'A3' => 130000.0];
            });
        $this->assertEquals(230000, app(BookingService::class)->calculateTicketTotal($showtime, collect(['A2', 'A3'])));
    }

    public function test_room_cinema_and_all_scopes_only_change_the_selected_rooms(): void
    {
        $room = $this->room();
        $sameCinema = $this->room($room->cinema);
        $otherCinema = $this->room();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->patch(route('admin.seats.type-prices', $room), ['scope' => 'room', 'fees' => $this->fees(1000)])->assertSessionHas('success');
        $this->assertDatabaseHas('seats', ['room_id' => $room->id, 'seat_type' => 'normal', 'extra_price' => 1000]);
        $this->assertDatabaseHas('seats', ['room_id' => $sameCinema->id, 'seat_type' => 'vip', 'extra_price' => 0]);
        $this->patch(route('admin.seats.type-prices', $room), ['scope' => 'cinema', 'fees' => $this->fees(2000)])->assertSessionHas('success');
        $this->assertDatabaseHas('seats', ['room_id' => $sameCinema->id, 'seat_type' => 'normal', 'extra_price' => 2000]);
        $this->assertDatabaseHas('seats', ['room_id' => $otherCinema->id, 'seat_type' => 'vip', 'extra_price' => 0]);
        $this->patch(route('admin.seats.type-prices', $room), ['scope' => 'all', 'fees' => $this->fees(3000)])->assertSessionHas('success');
        foreach ([$room, $sameCinema, $otherCinema] as $target) {
            $this->assertDatabaseHas('seats', ['room_id' => $target->id, 'seat_type' => 'normal', 'extra_price' => 3000]);
            $this->assertDatabaseHas('seats', ['room_id' => $target->id, 'seat_type' => 'sweetbox', 'extra_price' => 50000]);
        }
    }

    public function test_held_rooms_are_skipped_until_the_hold_expires(): void
    {
        Cache::flush();
        $room = $this->room();
        $protected = $this->room();
        $showtime = $this->showtime($protected);
        app(BookingHoldService::class)->start(123, $showtime->id, collect(['A2']));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.seats.type-prices', $room), ['scope' => 'all', 'fees' => $this->fees()])
            ->assertSessionHas('success')->assertSessionHas('seat_price_skipped', fn ($rooms) => count($rooms) === 1);
        $this->assertDatabaseHas('seats', ['room_id' => $protected->id, 'seat_type' => 'vip', 'extra_price' => 0]);
        $this->travel(11)->minutes();
        $this->patch(route('admin.seats.type-prices', $protected), ['scope' => 'room', 'fees' => $this->fees()])->assertSessionHas('success');
        $this->assertDatabaseHas('seats', ['room_id' => $protected->id, 'seat_type' => 'vip', 'extra_price' => 20000]);
    }

    public function test_invalid_prices_and_unauthorized_accounts_cannot_change_prices(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([-1, 1000000, 1.5, 'invalid'] as $price) {
            $this->patch(route('admin.seats.type-prices', $room), ['scope' => 'room', 'fees' => ['normal' => $price, 'vip' => 20000, 'sweetbox' => 50000]])
                ->assertSessionHasErrors('fees.normal');
        }
        $this->patch(route('admin.seats.type-prices', $room), ['scope' => 'unknown', 'fees' => $this->fees()])->assertSessionHasErrors('scope');
        $this->patch(route('admin.seats.type-prices', $room), ['scope' => 'room', 'fees' => ['normal' => 0]])->assertSessionHasErrors(['fees.vip', 'fees.sweetbox']);
        foreach (['staff', 'customer'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->patch(route('admin.seats.type-prices', $room), ['scope' => 'all', 'fees' => $this->fees()])->assertForbidden();
        }
        $this->assertEquals(0, $room->seats()->sum('extra_price'));
    }
}
