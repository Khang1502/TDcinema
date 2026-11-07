<?php

namespace Tests\Feature;

use App\Models\{Cinema, Room, Seat, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSeatCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function room(string $cinemaName): Room
    {
        $cinema = Cinema::create(['name' => $cinemaName, 'address' => 'Test', 'city' => 'HCM']);

        return Room::create(['cinema_id' => $cinema->id, 'name' => 'Rạp 1', 'type' => '2D', 'total_seats' => 0]);
    }

    private function payload(Room $room): array
    {
        return ['cinema_id' => $room->cinema_id, 'room_id' => $room->id, 'seat_row' => 'A',
            'seat_number' => 1, 'seat_type' => 'normal', 'extra_price' => 0, 'status' => 1];
    }

    public function test_create_lists_cinemas_and_only_rooms_from_the_selected_cinema(): void
    {
        $first = $this->room('Rạp A');
        $second = $this->room('Rạp B');

        $this->get(route('admin.seats.create', ['cinema_id' => $second->cinema_id, 'room_id' => $second->id]))
            ->assertOk()->assertSee('name="cinema_id"', false)->assertSee('Rạp A')->assertSee('Rạp B')
            ->assertViewHas('selectedCinemaId', $second->cinema_id)
            ->assertViewHas('selectedRoomId', $second->id)
            ->assertViewHas('availableRooms', fn ($rooms) => $rooms->modelKeys() === [$second->id]);

        $this->get(route('admin.seats.create', ['room_id' => $first->id]))
            ->assertOk()->assertViewHas('selectedCinemaId', $first->cinema_id)->assertViewHas('selectedRoomId', $first->id);
        $this->get(route('admin.seats.index', ['cinema_id' => $second->cinema_id, 'room_id' => $second->id]))
            ->assertOk()->assertSee(e(route('admin.seats.create', ['cinema_id' => $second->cinema_id, 'room_id' => $second->id])), false);
    }

    public function test_old_selection_takes_priority_over_the_page_query_after_validation_errors(): void
    {
        $first = $this->room('Rạp A');
        $second = $this->room('Rạp B');

        $this->withSession(['_old_input' => $this->payload($second)])
            ->get(route('admin.seats.create', ['cinema_id' => $first->cinema_id, 'room_id' => $first->id]))
            ->assertOk()->assertViewHas('selectedCinemaId', $second->cinema_id)
            ->assertViewHas('selectedRoomId', $second->id);
    }

    public function test_missing_or_mismatched_selection_is_not_implicitly_replaced_with_another_room(): void
    {
        $first = $this->room('Rạp A');
        $second = $this->room('Rạp B');

        $this->get(route('admin.seats.create'))->assertOk()
            ->assertViewHas('selectedCinemaId', null)->assertViewHas('selectedRoomId', null)
            ->assertViewHas('availableRooms', fn ($rooms) => $rooms->isEmpty());
        $this->get(route('admin.seats.create', ['cinema_id' => $first->cinema_id, 'room_id' => $second->id]))
            ->assertOk()->assertViewHas('selectedRoomId', null)
            ->assertViewHas('availableRooms', fn ($rooms) => $rooms->modelKeys() === [$first->id]);
        $this->get(route('admin.seats.create', ['cinema_id' => 99999, 'room_id' => 99999]))
            ->assertOk()->assertViewHas('selectedCinemaId', null)->assertViewHas('selectedRoomId', null);
    }

    public function test_empty_cinema_and_no_cinemas_have_clear_empty_states(): void
    {
        $this->get(route('admin.seats.create'))->assertOk()->assertSee('Chưa có rạp.');
        $cinema = Cinema::create(['name' => 'Rạp chưa có phòng', 'address' => 'Test', 'city' => 'HCM']);
        $this->get(route('admin.seats.create', ['cinema_id' => $cinema->id]))->assertOk()
            ->assertSee('Rạp này chưa có phòng chiếu.')->assertViewHas('selectedCinemaId', $cinema->id)
            ->assertViewHas('availableRooms', fn ($rooms) => $rooms->isEmpty());
    }

    public function test_store_rejects_rooms_from_another_cinema_and_missing_cinema(): void
    {
        $first = $this->room('Rạp A');
        $second = $this->room('Rạp B');
        $payload = $this->payload($first);
        $payload['room_id'] = $second->id;
        $this->post(route('admin.seats.store'), $payload)->assertSessionHasErrors('room_id');
        unset($payload['cinema_id']);
        $this->post(route('admin.seats.store'), $payload)->assertSessionHasErrors('cinema_id');
        $this->assertDatabaseCount('seats', 0);
    }

    public function test_store_creates_seat_in_selected_room_updates_capacity_and_returns_to_that_room(): void
    {
        $room = $this->room('Rạp A');
        $this->post(route('admin.seats.store'), $this->payload($room))
            ->assertSessionHasNoErrors()->assertSessionHas('success')
            ->assertRedirect(route('admin.seats.index', ['cinema_id' => $room->cinema_id, 'room_id' => $room->id]));
        $this->assertDatabaseHas('seats', ['room_id' => $room->id, 'seat_row' => 'A', 'seat_number' => 1]);
        $this->assertEquals(1, $room->fresh()->total_seats);

        $this->post(route('admin.seats.store'), $this->payload($room))->assertSessionHasErrors('seat_number');
        $this->assertDatabaseCount('seats', 1);
    }

    public function test_existing_edit_flow_does_not_require_the_new_cinema_field(): void
    {
        $room = $this->room('Rạp A');
        $seat = Seat::create($this->payload($room));
        $payload = $this->payload($room);
        unset($payload['cinema_id']);
        $payload['seat_type'] = 'vip';
        $this->put(route('admin.seats.update', $seat), $payload)->assertSessionHasNoErrors();
        $this->assertSame('vip', $seat->fresh()->seat_type);
    }
}
