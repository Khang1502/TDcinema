<?php

namespace Tests\Feature;

use App\Models\{Cinema, Movie, Room, Seat, Showtime, Ticket, User};
use App\Services\BookingHoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminSeatApplyAllTest extends TestCase
{
    use RefreshDatabase;

    private function room(?Cinema $cinema = null): Room
    {
        $cinema ??= Cinema::create(['name' => 'Rạp ' . Cinema::count(), 'address' => 'Test', 'city' => 'HCM']);
        return Room::create(['cinema_id' => $cinema->id, 'name' => 'Phòng ' . Room::count(), 'type' => '2D', 'total_seats' => 99]);
    }

    private function seat(Room $room, int $number = 1, array $attributes = []): Seat
    {
        return Seat::create(array_merge([
            'room_id' => $room->id, 'seat_row' => 'A', 'seat_number' => $number,
            'seat_type' => 'normal', 'extra_price' => 0, 'status' => 1,
        ], $attributes));
    }

    private function showtime(Room $room): Showtime
    {
        $movie = Movie::create(['title' => 'Phim test', 'duration' => 100, 'status' => 'now_showing']);
        return Showtime::create([
            'room_id' => $room->id, 'movie_id' => $movie->id,
            'start_time' => now()->addHour(), 'end_time' => now()->addHours(3),
            'base_price' => 80000, 'status' => 'active',
        ]);
    }

    public function test_layout_is_applied_to_rooms_in_all_cinemas_with_aisles_and_totals(): void
    {
        $source = $this->room();
        $this->seat($source, 1, ['seat_type' => 'vip', 'extra_price' => 20000]);
        $this->seat($source, 3, ['seat_type' => 'sweetbox', 'status' => 0, 'extra_price' => 40000]);
        $sameCinema = $this->room($source->cinema);
        $otherCinema = $this->room();
        $matchingSeat = $this->seat($sameCinema);
        $removedSeat = $this->seat($sameCinema, 2);
        $sourceIds = $source->seats()->pluck('id')->all();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.seats.index', ['cinema_id' => $source->cinema_id, 'room_id' => $source->id]))
            ->assertOk()->assertSee('Áp dụng sơ đồ ghế cho tất cả các rạp');
        $this->post(route('admin.seats.apply-all', $source), ['confirm_apply_all' => 1])
            ->assertRedirect(route('admin.seats.index', ['cinema_id' => $source->cinema_id, 'room_id' => $source->id]))
            ->assertSessionHas('success', fn ($message) => str_contains($message, '2 phòng chiếu thuộc 2 rạp'));

        foreach ([$sameCinema, $otherCinema] as $target) {
            $this->assertEquals([1, 3], $target->seats()->orderBy('seat_number')->pluck('seat_number')->all());
            $this->assertDatabaseHas('seats', ['room_id' => $target->id, 'seat_number' => 1, 'seat_type' => 'vip', 'extra_price' => 20000, 'status' => 1]);
            $this->assertDatabaseHas('seats', ['room_id' => $target->id, 'seat_number' => 3, 'seat_type' => 'sweetbox', 'extra_price' => 40000, 'status' => 0]);
            $this->assertEquals(2, $target->fresh()->total_seats);
        }
        $this->assertDatabaseHas('seats', ['id' => $matchingSeat->id, 'seat_type' => 'vip']);
        $this->assertDatabaseMissing('seats', ['id' => $removedSeat->id]);
        $this->assertSame($sourceIds, $source->seats()->pluck('id')->all());
        $this->assertEquals(99, $source->fresh()->total_seats);

        // Applying again keeps seat IDs and does not create duplicates.
        $targetIds = $otherCinema->seats()->orderBy('id')->pluck('id')->all();
        $this->post(route('admin.seats.apply-all', $source), ['confirm_apply_all' => 1])->assertSessionHas('success');
        $this->assertSame($targetIds, $otherCinema->seats()->orderBy('id')->pluck('id')->all());
    }

    public function test_rooms_with_tickets_are_skipped_without_deleting_history(): void
    {
        $source = $this->room();
        $this->seat($source, 3);
        $protected = $this->room();
        $seat = $this->seat($protected);
        $ticket = Ticket::create([
            'user_id' => User::factory()->create()->id, 'showtime_id' => $this->showtime($protected)->id,
            'seat_id' => $seat->id, 'price' => 80000, 'status' => 'confirmed', 'booked_at' => now(),
        ]);
        $eligible = $this->room();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.seats.apply-all', $source), ['confirm_apply_all' => 1])
            ->assertSessionHas('success')->assertSessionHas('seat_layout_skipped', fn ($rooms) => count($rooms) === 1 && str_contains($rooms[0], 'đã có vé'));
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'seat_id' => $seat->id]);
        $this->assertDatabaseHas('seats', ['id' => $seat->id, 'seat_number' => 1]);
        $this->assertEquals(99, $protected->fresh()->total_seats);
        $this->assertDatabaseHas('seats', ['room_id' => $eligible->id, 'seat_number' => 3]);
    }

    public function test_active_seat_holds_are_preserved_and_expired_holds_do_not_block_application(): void
    {
        Cache::flush();
        $source = $this->room();
        $this->seat($source, 3);
        $target = $this->room();
        $seat = $this->seat($target);
        $showtime = $this->showtime($target);
        $hold = app(BookingHoldService::class)->start(123, $showtime->id, collect(['A1']));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.seats.apply-all', $source), ['confirm_apply_all' => 1])
            ->assertSessionHas('error')->assertSessionHas('seat_layout_skipped', fn ($rooms) => str_contains($rooms[0], 'giữ ghế'));
        $this->assertDatabaseHas('seats', ['id' => $seat->id]);
        $this->assertSame($hold['token'], Cache::get("booking-hold:{$showtime->id}:A1")['token']);

        $this->travel(11)->minutes();
        $this->post(route('admin.seats.apply-all', $source), ['confirm_apply_all' => 1])->assertSessionHas('success');
        $this->assertDatabaseHas('seats', ['room_id' => $target->id, 'seat_number' => 3]);
        $this->assertDatabaseMissing('seats', ['id' => $seat->id]);
    }

    public function test_empty_source_and_unconfirmed_application_do_not_change_rooms(): void
    {
        $source = $this->room();
        $target = $this->room();
        $seat = $this->seat($target);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.seats.apply-all', $source))->assertSessionHasErrors('confirm_apply_all');
        $this->post(route('admin.seats.apply-all', $source), ['confirm_apply_all' => 1])->assertSessionHas('error');
        $this->assertDatabaseHas('seats', ['id' => $seat->id]);
        $this->assertEquals(99, $target->fresh()->total_seats);
    }

    public function test_database_hold_checks_do_not_grow_with_the_number_of_showtimes(): void
    {
        Schema::rename('cache', 'seat_test_cache');
        config([
            'cache.default' => 'database',
            'cache.prefix' => 'seat_test:',
            'cache.stores.database.connection' => 'sqlite',
            'cache.stores.database.table' => 'seat_test_cache',
        ]);
        Cache::purge('database');
        $source = $this->room();
        $this->seat($source, 3);
        $eligible = $this->room();
        $this->seat($eligible);
        $protected = $this->room();
        $protectedSeat = $this->seat($protected);
        $template = $this->showtime($eligible)->getAttributes();
        unset($template['id']);
        foreach (array_chunk(array_fill(0, 1000, $template), 100) as $chunk) {
            DB::table('showtimes')->insert($chunk);
        }
        $showtime = $this->showtime($protected);
        $hold = app(BookingHoldService::class)->start(123, $showtime->id, collect(['A1']));

        // Unrelated applications and expired payloads must not block a room.
        $eligibleShowtimeId = $eligible->showtimes()->first()->id;
        DB::table('seat_test_cache')->insert([
            'key' => "another-app:booking-hold:{$eligibleShowtimeId}:A1",
            'value' => serialize(['expires_at' => now()->addMinutes(10)->timestamp]),
            'expiration' => now()->addMinutes(10)->timestamp,
        ]);
        Cache::put("booking-hold:{$eligibleShowtimeId}:A1", ['expires_at' => now()->subMinute()->timestamp], 600);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        DB::enableQueryLog();
        $this->post(route('admin.seats.apply-all', $source), ['confirm_apply_all' => 1])
            ->assertRedirect()->assertSessionHas('success')
            ->assertSessionHas('seat_layout_skipped', fn ($rooms) => count($rooms) === 1 && str_contains($rooms[0], 'giữ ghế'));
        $cacheQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'seat_test_cache'));
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(2, $cacheQueries->count(), 'Only stored holds should be queried, regardless of showtime count.');
        $this->assertDatabaseHas('seats', ['room_id' => $eligible->id, 'seat_number' => 3]);
        $this->assertDatabaseHas('seats', ['id' => $protectedSeat->id]);
        $this->assertSame($hold['token'], Cache::get("booking-hold:{$showtime->id}:A1")['token']);

        $this->travel(11)->minutes();
        $this->post(route('admin.seats.apply-all', $source), ['confirm_apply_all' => 1])->assertSessionHas('success');
        $this->assertDatabaseHas('seats', ['room_id' => $protected->id, 'seat_number' => 3]);
        $this->assertDatabaseMissing('seats', ['id' => $protectedSeat->id]);
    }

    public function test_staff_without_seat_permission_cannot_apply_layouts(): void
    {
        $source = $this->room();
        $this->seat($source);
        $target = $this->room();
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->post(route('admin.seats.apply-all', $source), ['confirm_apply_all' => 1])->assertForbidden();
        $this->assertEquals(0, $target->seats()->count());
    }
}
